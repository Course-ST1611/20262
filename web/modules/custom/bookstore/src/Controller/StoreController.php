<?php

declare(strict_types=1);

namespace Drupal\bookstore\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\bookstore\Form\AddToCartForm;
use Drupal\bookstore\Form\CartItemForm;
use Drupal\bookstore\Form\CheckoutForm;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class StoreController extends ControllerBase {
  private function store(): \Drupal\bookstore\Store {
    return \Drupal::service('bookstore.store');
  }

  private function themePath(): string {
    return base_path() . \Drupal::service('extension.list.theme')->getPath('bookstore_lux');
  }

  private function render(string $theme, array $variables): array {
    return ['#theme' => $theme] + array_combine(array_map(static fn($key) => '#' . $key, array_keys($variables)), array_values($variables)) + ['#cache' => ['max-age' => 0]];
  }

  private function decorate(array $book): array {
    $book['price_display'] = number_format((float) $book['price'], 0, ',', '.');
    $book['url'] = Url::fromRoute('bookstore.book', ['isbn' => $book['isbn']])->toString();
    $book['image_url'] = $book['image'] ?: $this->themePath() . '/images/book-placeholder.svg';
    return $book;
  }

  public function home(): array {
    return $this->render('bookstore_home', ['books' => array_map($this->decorate(...), $this->store()->books()), 'theme_path' => $this->themePath()]);
  }

  public function book(string $isbn): array {
    $book = $this->store()->book($isbn);
    if (!$book) {
      throw new NotFoundHttpException();
    }
    return $this->render('bookstore_book', ['book' => $this->decorate($book), 'theme_path' => $this->themePath(), 'form' => $this->currentUser()->isAuthenticated() && $this->store()->currentCustomer() ? $this->formBuilder()->getForm(AddToCartForm::class, $isbn) : NULL]);
  }

  public function stores(): array {
    $stores = [
      ['name' => 'BookStore Co. El Poblado', 'city' => 'Medellín, Antioquia', 'address' => 'Carrera 43A # 6 Sur - 26', 'schedule' => 'Lunes a sábado: 10:00 a.m. - 8:00 p.m.', 'phone' => '+57 604 444 0101'],
      ['name' => 'BookStore Co. Zona T', 'city' => 'Bogotá, D.C.', 'address' => 'Carrera 13 # 82 - 24', 'schedule' => 'Lunes a sábado: 10:00 a.m. - 8:00 p.m.', 'phone' => '+57 601 444 0102'],
      ['name' => 'BookStore Co. Granada', 'city' => 'Cali, Valle del Cauca', 'address' => 'Avenida 9 Norte # 14N - 48', 'schedule' => 'Lunes a sábado: 10:00 a.m. - 8:00 p.m.', 'phone' => '+57 602 444 0103'],
    ];
    return $this->render('bookstore_stores', ['stores' => $stores, 'theme_path' => $this->themePath()]);
  }

  public function profile(): array {
    $customer = $this->store()->currentCustomer();
    if (!$customer) {
      throw new AccessDeniedHttpException();
    }
    return $this->render('bookstore_profile', ['customer' => $customer]);
  }

  public function cart(): array {
    if (!$this->store()->currentCustomer()) {
      throw new AccessDeniedHttpException();
    }
    $cart = \Drupal::request()->getSession()->get('bookstore.cart', []);
    $items = [];
    $subtotal = 0.0;
    $count = 0;
    foreach ($cart as $isbn => $quantity) {
      $book = $this->store()->book((string) $isbn);
      if (!$book) {
        continue;
      }
      $book = $this->decorate($book);
      $book['quantity'] = (int) $quantity;
      $book['update_form'] = $this->formBuilder()->getForm(CartItemForm::class, $isbn);
      $items[] = $book;
      $subtotal += (float) $book['price'] * $quantity;
      $count += $quantity;
    }
    return $this->render('bookstore_cart', ['items' => $items, 'subtotal' => number_format($subtotal, 0, ',', '.'), 'count' => $count, 'checkout' => $this->formBuilder()->getForm(CheckoutForm::class)]);
  }

  public function orders(): array {
    if (!$this->store()->currentCustomer()) {
      throw new AccessDeniedHttpException();
    }
    $orders = $this->store()->orders();
    foreach ($orders as &$order) {
      $order = $this->decorateOrder($order);
    }
    return $this->render('bookstore_orders', ['orders' => $orders]);
  }

  public function success(string $order_id): array {
    $order = $this->store()->order($order_id);
    if (!$order) {
      throw new NotFoundHttpException();
    }
    return $this->render('bookstore_success', ['order' => $this->decorateOrder($order)]);
  }

  private function decorateOrder(array $order): array {
    $order['total_display'] = number_format((float) $order['total_amount'], 0, ',', '.');
    $order['date_display'] = date('d/m/Y H:i', strtotime($order['order_timestamp']));
    $order['quantity'] = 0;
    foreach ($order['items'] as &$item) {
      $order['quantity'] += (int) $item['quantity'];
      $item['unit_display'] = number_format((float) $item['unit_price'], 0, ',', '.');
      $item['line_display'] = number_format((float) $item['line_total'], 0, ',', '.');
    }
    return $order;
  }
}
