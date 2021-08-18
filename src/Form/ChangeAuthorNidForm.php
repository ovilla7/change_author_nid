<?php

namespace Drupal\change_author_nid\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\SessionManagerInterface;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Routing\RouteBuilderInterface;

/**
 * ChangeAuthorNidForm.
 */
class ChangeAuthorNidForm extends FormBase implements FormInterface {

  /**
   * Set a var to make stepthrough form.
   */
  protected $step = 1;
  /**
   * Keep track of user input.
   */
  protected $userInput = [];

  /**
   * Session.
   */
  private $sessionManager;

  /**
   * User.
   */
  private $currentUser;

  /**
   * The route builder.
   */
  protected $routeBuilder;

  /**
   * Constructs a \Drupal\change_author_nid\Form\BulkUpdateFieldsForm.
   *
   * @param \Drupal\Core\Session\SessionManagerInterface $session_manager
   *   Session.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   User.
   * @param \Drupal\Core\Routing\RouteBuilderInterface $route_builder
   *   Route.
   */
  public function __construct(SessionManagerInterface $session_manager, AccountInterface $current_user, RouteBuilderInterface $route_builder) {
    $this->sessionManager = $session_manager;
    $this->currentUser = $current_user;
    $this->routeBuilder = $route_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('session_manager'),
      $container->get('current_user'),
      $container->get('router.builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'change_author_nid_form';
  }

  /**
   * {@inheritdoc}
   */
  public function updateFields() {
    $node_ids = explode(",", $this->userInput['node_ids']);
    $entities = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($node_ids);
    $new_author = $this->userInput['new_author'];
    $user = User::load($new_author);

    $a = 1;
    $batch = [
      'title' => $this->t(
        'Changing author on @count_entities nodes, to new author @author_name',
        [
          '@count_entities' => count($entities),
          '@author_name' => $user->label()
        ]
      ),
      'operations' => [
        [
          '\Drupal\change_author_nid\ChangeAuthorNid::updateFields',
          [$entities, $new_author],
        ],
      ],
      'finished' => '\Drupal\change_author_nid\ChangeAuthorNid::changeAuthorNidFinishedCallback',
    ];
    batch_set($batch);

    return $this->t(
      'Author successfully changed on @count_entities nodes, to new author @author_name', 
      [
        '@count_entities' => count($entities),
        '@author_name' => $user->label()
      ]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    switch ($this->step) {
      case 1:
        $form_state->setRebuild();
        break;

      case 2:
        if (method_exists($this, 'updateFields')) {
          $return_verify = $this->updateFields();
        }
        \Drupal::messenger()->addStatus($return_verify);
        $this->routeBuilder->rebuild();
        break;
    }
    $this->step++;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    if (isset($this->form)) {
      $form = $this->form;
    }
    $form['#title'] = $this->t('Choose new author for selected items');
    $submit_label = 'Next';

    switch ($this->step) {
      case 1:
        $form['new_author'] = [
          '#title' => $this->t('Choose new author'),
          '#type' => 'entity_autocomplete',
          '#target_type' => 'user',
          '#required' => TRUE,
          '#selection_settings' => [
            'include_anonymous' => FALSE,
          ],
        ];
        $form['node_ids'] = [
          '#title' => $this->t('Nodes ids'),
          '#description' => $this->t('Comma separated values of node ids, without space between each id, i.e.: 2,3,7,9'),
          '#type' => 'textarea',
          '#required' => TRUE,
        ];
        break;

      case 2:
        $uid = $form_state->getValue('new_author');
        $node_ids = $form_state->getValue('node_ids');
        $this->userInput['new_author'] = $uid;
        $this->userInput['node_ids'] = $node_ids;
        $user = User::load($uid);
        $form['label_confirmation'] = [
          '#type' => 'label',
          '#title' => $this->t(
            'Are you sure you want to alter the author to @author_name on @count_entities entities?',
            [
              '@author_name' => $user->label(),
              '@count_entities' => count(explode(",", $node_ids)),
            ]
          )
        ];
        
        $submit_label = $this->t('Change author on selected entities');

        break;
    }
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $submit_label,
      '#button_type' => 'primary',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  /* public function validateForm(array &$form, FormStateInterface $form_state) {
    // TODO.
  } */

}
