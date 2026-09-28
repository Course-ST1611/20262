<?php

declare(strict_types=1);

namespace Drupal\bookstore\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

final class CartItemForm extends FormBase {
  public function getFormId(): string { return 'bookstore_cart_item'; }

  public function buildForm(array $form, FormStateInterface $form_state, ?string $isbn = NULL): array {
    $cart = \Drupal::request()->getSession()->get('bookstore.cart', []);
    $book = \Drupal::service('bookstore.store')->book((string) $isbn);
    if (!$book || !isset($cart[$isbn])) { return $form; }
    $form_state->set('isbn', $isbn);
    $form['#attributes']['class'][] = 'bookstore-cart-item-form';
    $form['quantity'] = ['#type' => 'number', '#title' => $this->t('Cantidad'), '#title_display' => 'invisible', '#default_value' => $cart[$isbn], '#min' => 1, '#max' => max(1, (int) $book['stock']), '#required' => TRUE, '#attributes' => ['class' => ['form-control', 'form-control-sm']]];
    $form['update'] = ['#type' => 'submit', '#value' => $this->t('Actualizar'), '#attributes' => ['class' => ['btn', 'btn-outline-secondary', 'btn-sm']]];
    $form['remove'] = ['#type' => 'submit', '#value' => $this->t('Eliminar'), '#submit' => ['::removeItem'], '#limit_validation_errors' => [], '#attributes' => ['class' => ['btn', 'btn-outline-danger', 'btn-sm']]];
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $book = \Drupal::service('bookstore.store')->book($form_state->get('isbn'));
    $quantity = (int) $form_state->getValue('quantity');
    if (!$book || $quantity < 1 || $quantity > (int) $book['stock']) {
      $form_state->setErrorByName('quantity', $this->t('La cantidad supera las existencias disponibles.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $session = \Drupal::request()->getSession();
    $cart = $session->get('bookstore.cart', []);
    $cart[$form_state->get('isbn')] = (int) $form_state->getValue('quantity');
    $session->set('bookstore.cart', $cart);
    $form_state->setRedirect('bookstore.cart');
  }

  public function removeItem(array &$form, FormStateInterface $form_state): void {
    $session = \Drupal::request()->getSession();
    $cart = $session->get('bookstore.cart', []);
    unset($cart[$form_state->get('isbn')]);
    $session->set('bookstore.cart', $cart);
    $form_state->setRedirect('bookstore.cart');
  }
}
