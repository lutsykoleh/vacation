<?php

namespace Drupal\workflow\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\user\UserStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the CommentName constraint.
 *
 * @see https://drupalwatchdog.com/volume-5/issue-2/introducing-drupal-8s-entity-validation-api
 */
class WorkflowFieldConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * Validator 2.5 and upwards compatible execution context.
   *
   * @var \Symfony\Component\Validator\Context\ExecutionContextInterface
   */
  protected $context;

  /**
   * User storage handler.
   *
   * @var \Drupal\user\UserStorageInterface
   */
  protected $userStorage;

  /**
   * Constructs a new Validator.
   *
   * @param \Drupal\user\UserStorageInterface $user_storage
   *   The user storage handler.
   */
  public function __construct(UserStorageInterface $user_storage) {
    $this->userStorage = $user_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('entity_type.manager')->getStorage('user'));
  }

  /**
   * {@inheritdoc}
   */
  public function validate($entity, Constraint $constraint) {
    // Workflow field name on CommentForm has special requirements.
    /** @var \Drupal\field\Entity\FieldStorageConfig $field_storage */
    $field_storage = $entity->getFieldDefinition()->getFieldStorageDefinition();
    if (!$this->isValidFieldname($field_storage)) {
      $this->context->buildViolation($constraint->messageFieldname)
        ->atPath('fieldnameOnComment')
        ->addViolation();
    }
  }

  /**
   * @param \Drupal\Core\Field\FieldStorageDefinitionInterface $field_storage
   *
   * @return bool
   */
  protected function isValidFieldname(FieldStorageDefinitionInterface $field_storage) {
    if ($field_storage->getTargetEntityTypeId() !== 'comment') {
      return TRUE;
    }

    $field_name = $field_storage->getName();
    // Check if the 'comment' field name exists on the 'commented' entity type.
    // @todo Fix field on a non-relevant entity_type.
    $comment_field_name_ok = FALSE;
    foreach (_workflow_info_fields() as $info) {
      if (($info->getName() == $field_name) && ($info->getTargetEntityTypeId() !== 'comment')) {
        $comment_field_name_ok = TRUE;
      }
    }

    return $comment_field_name_ok;
  }

}
