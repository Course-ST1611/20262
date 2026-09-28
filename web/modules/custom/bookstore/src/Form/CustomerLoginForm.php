<?php

declare(strict_types=1);

namespace Drupal\bookstore\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;

/** Email-only sign-in for the synthetic, classroom-only customer dataset. */
final class CustomerLoginForm extends FormBase {
  public function getFormId(): string { return 'bookstore_customer_login'; }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    if ($this->currentUser()->isAuthenticated()) {
      $form['notice'] = ['#markup' => '<p>Ya has iniciado sesión.</p>'];
      return $form;
    }
    $form['#prefix'] = '<div class="bookstore-login d-flex justify-content-center align-items-center"><div class="card shadow rounded-4 p-4 bookstore-login-card"><div class="card-body"><h1 class="h3 text-center mb-4">Iniciar sesión</h1>';
    $form['#suffix'] = '</div></div></div>';
    $form['email'] = ['#type' => 'email', '#title' => $this->t('Correo electrónico'), '#required' => TRUE, '#attributes' => ['autocomplete' => 'email', 'class' => ['form-control']]];
    $form['submit'] = ['#type' => 'submit', '#value' => $this->t('Ingresar'), '#attributes' => ['class' => ['btn', 'btn-primary', 'w-100', 'mt-4', 'rounded-pill']]];
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $email = trim((string) $form_state->getValue('email'));
    $customer = \Drupal::service('bookstore.store')->customerByEmail($email);
    if (!$customer) {
      $form_state->setErrorByName('email', $this->t('No se encontró un cliente con ese correo.'));
      return;
    }
    $form_state->set('customer', $customer);
    $accounts = \Drupal::entityTypeManager()->getStorage('user')->loadByProperties(['mail' => $customer['email']]);
    foreach ($accounts as $account) {
      $linked = \Drupal::service('user.data')->get('bookstore', (int) $account->id(), 'customer_id');
      if (!$linked || $linked !== $customer['customer_id'] || $account->isBlocked()) {
        $form_state->setErrorByName('email', $this->t('Esta cuenta requiere revisión administrativa.'));
      }
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $customer = $form_state->get('customer');
    $storage = \Drupal::entityTypeManager()->getStorage('user');
    $accounts = $storage->loadByProperties(['mail' => $customer['email']]);
    $account = reset($accounts);
    if (!$account) {
      $account = User::create(['name' => 'customer-' . $customer['customer_id'], 'mail' => $customer['email'], 'pass' => bin2hex(random_bytes(32)), 'status' => 1]);
      $account->save();
      \Drupal::service('user.data')->set('bookstore', (int) $account->id(), 'customer_id', $customer['customer_id']);
    }
    user_login_finalize($account);
    \Drupal::request()->getSession()->remove('bookstore.cart');
    $destination = \Drupal::request()->query->get('destination');
    if (is_string($destination) && str_starts_with($destination, '/') && !str_starts_with($destination, '//')) {
      $form_state->setRedirectUrl(\Drupal\Core\Url::fromUserInput($destination));
    }
    else {
      $form_state->setRedirect('bookstore.home');
    }
  }
}
