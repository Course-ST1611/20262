<?php

declare(strict_types=1);

namespace Drupal\bookstore\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

final class AddToCartForm extends FormBase {
  public function getFormId(): string { return 'bookstore_add_to_cart'; }

  public function buildForm(array $form, FormStateInterface $form_state, ?string $isbn = NULL): array {
    $book = \Drupal::service('bookstore.store')->book((string) $isbn);
    if (!$book) { return $form; }
    $form_state->set('isbn', $isbn);
    $form['quantity'] = ['#type' => 'number', '#title' => $this->t('Cantidad'), '#default_value' => 1, '#min' => 1, '#max' => max(1, (int) $book['stock']), '#required' => TRUE];
    $form['submit'] = ['#type' => 'submit', '#value' => $this->t('Agregar al carrito'), '#disabled' => (int) $book['stock'] === 0, '#attributes' => ['class' => ['btn', 'btn-primary', 'w-100']]];
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $isbn = $form_state->get('isbn');
    $book = \Drupal::service('bookstore.store')->book($isbn);
    $cart = \Drupal::request()->getSession()->get('bookstore.cart', []);
    $quantity = (int) $form_state->getValue('quantity');
    if (!$book || $quantity < 1 || $quantity + ($cart[$isbn] ?? 0) > (int) $book['stock']) {
      $form_state->setErrorByName('quantity', $this->t('La cantidad supera las existencias disponibles.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $isbn = $form_state->get('isbn');
    $session = \Drupal::request()->getSession();
    $cart = $session->get('bookstore.cart', []);
    $cart[$isbn] = ($cart[$isbn] ?? 0) + (int) $form_state->getValue('quantity');
    $session->set('bookstore.cart', $cart);
    $this->messenger()->addStatus($this->t('El libro fue agregado al carrito.'));
    $form_state->setRedirect('bookstore.book', ['isbn' => $isbn]);
  }
}
