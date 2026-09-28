<?php

declare(strict_types=1);

namespace Drupal\bookstore\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

final class CheckoutForm extends FormBase {
  public function getFormId(): string { return 'bookstore_checkout'; }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['submit'] = ['#type' => 'submit', '#value' => $this->t('Finalizar compra'), '#attributes' => ['class' => ['btn', 'btn-primary', 'w-100']]];
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $session = \Drupal::request()->getSession();
    $cart = $session->get('bookstore.cart', []);
    if (!$cart) {
      $this->messenger()->addError($this->t('El carrito está vacío.'));
      $form_state->setRedirect('bookstore.cart');
      return;
    }
    try {
      $id = \Drupal::service('bookstore.store')->placeOrder($cart);
      $session->remove('bookstore.cart');
      $form_state->setRedirect('bookstore.success', ['order_id' => $id]);
    }
    catch (\Throwable $e) {
      \Drupal::logger('bookstore')->error('Order failed: @error', ['@error' => $e->getMessage()]);
      $this->messenger()->addError($this->t('No fue posible completar la compra. Verifica las existencias e inténtalo de nuevo.'));
      $form_state->setRedirect('bookstore.cart');
    }
  }
}
