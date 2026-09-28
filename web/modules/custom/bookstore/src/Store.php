<?php

declare(strict_types=1);

namespace Drupal\bookstore;

use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\user\UserDataInterface;

/**
 * Accesses the legacy operational tables without changing their contract.
 */
final class Store {
  public function __construct(
    private readonly Connection $db,
    private readonly AccountProxyInterface $account,
    private readonly UserDataInterface $userData,
  ) {}

  public function books(): array {
    return $this->db->query('SELECT * FROM books WHERE deleted_at IS NULL ORDER BY title')->fetchAll(\PDO::FETCH_ASSOC);
  }

  public function book(string $isbn): ?array {
    $row = $this->db->query('SELECT * FROM books WHERE isbn = :isbn AND deleted_at IS NULL', [':isbn' => $isbn])->fetchAssoc();
    return $row ?: NULL;
  }

  public function customerByEmail(string $email): ?array {
    $row = $this->db->query('SELECT * FROM customers WHERE LOWER(BTRIM(email)) = LOWER(BTRIM(:email)) AND deleted_at IS NULL', [':email' => $email])->fetchAssoc();
    return $row ?: NULL;
  }

  public function customerId(): ?string {
    if ($this->account->isAnonymous()) {
      return NULL;
    }
    return $this->userData->get('bookstore', (int) $this->account->id(), 'customer_id');
  }

  public function currentCustomer(): ?array {
    $id = $this->customerId();
    if (!$id) {
      return NULL;
    }
    $row = $this->db->query('SELECT * FROM customers WHERE customer_id = :id AND deleted_at IS NULL', [':id' => $id])->fetchAssoc();
    return $row ?: NULL;
  }

  public function order(string $id): ?array {
    $customer = $this->customerId();
    if (!$customer) {
      return NULL;
    }
    $order = $this->db->query('SELECT * FROM orders WHERE order_id = :id AND customer_id = :customer AND deleted_at IS NULL', [':id' => $id, ':customer' => $customer])->fetchAssoc();
    if (!$order) {
      return NULL;
    }
    $order['items'] = $this->db->query('SELECT i.*, b.title, b.author, b.isbn FROM order_items i JOIN books b ON b.book_id = i.book_id WHERE i.order_id = :id ORDER BY b.title', [':id' => $id])->fetchAll(\PDO::FETCH_ASSOC);
    return $order;
  }

  public function orders(): array {
    $id = $this->customerId();
    if (!$id) {
      return [];
    }
    $rows = $this->db->query('SELECT order_id FROM orders WHERE customer_id = :id AND deleted_at IS NULL ORDER BY order_timestamp DESC', [':id' => $id])->fetchCol();
    return array_values(array_filter(array_map($this->order(...), $rows)));
  }

  /**
   * Reprice and lock stock server-side; all writes roll back on any failure.
   */
  public function placeOrder(array $cart): string {
    $customer = $this->currentCustomer();
    if (!$customer || !$cart) {
      throw new \RuntimeException('El cliente o el carrito no están disponibles.');
    }
    $transaction = $this->db->startTransaction();
    try {
      $ids = array_keys($cart);
      sort($ids, SORT_STRING);
      $items = [];
      $total = '0';
      foreach ($ids as $isbn) {
        $quantity = (int) $cart[$isbn];
        if ($quantity < 1 || $quantity > 100) {
          throw new \RuntimeException('Cantidad inválida.');
        }
        $book = $this->db->query('SELECT * FROM books WHERE isbn = :isbn AND deleted_at IS NULL FOR UPDATE', [':isbn' => $isbn])->fetchAssoc();
        if (!$book || (int) $book['stock'] < $quantity) {
          throw new \RuntimeException('No hay existencias suficientes para completar la compra.');
        }
        $line = bcmul((string) $book['price'], (string) $quantity, 2);
        $total = bcadd($total, $line, 2);
        $items[] = [$book, $quantity];
      }
      $orderId = $this->db->query("INSERT INTO orders (customer_id, order_status, total_amount) VALUES (:customer, 'PLACED', :total) RETURNING order_id", [':customer' => $customer['customer_id'], ':total' => $total])->fetchField();
      foreach ($items as [$book, $quantity]) {
        $this->db->query('INSERT INTO order_items (order_id, book_id, quantity, unit_price) VALUES (:order, :book, :quantity, :price)', [':order' => $orderId, ':book' => $book['book_id'], ':quantity' => $quantity, ':price' => $book['price']]);
        $this->db->query('UPDATE books SET stock = stock - :quantity WHERE book_id = :id', [':quantity' => $quantity, ':id' => $book['book_id']]);
      }
      unset($transaction);
      return (string) $orderId;
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      throw $e;
    }
  }
}
