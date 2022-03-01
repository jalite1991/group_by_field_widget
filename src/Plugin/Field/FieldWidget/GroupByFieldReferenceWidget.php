<?php

namespace Drupal\group_by_field_widget\Plugin\Field\FieldWidget;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\OptionsWidgetBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;

/**
 * Plugin implementation of the 'group_by_field_reference_widget' widget.
 *
 * @FieldWidget(
 *   id = "group_by_field_reference_widget",
 *   module = "group_by_field_widget",
 *   label = @Translation("Group by field reference widget"),
 *   field_types = {
 *     "entity_reference"
 *   },
 *   multiple_values = TRUE
 * )
 */
class GroupByFieldReferenceWidget extends OptionsWidgetBase
{

  /**
   * Drupal\Core\Entity\EntityFieldManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   *
   * Drupal\Core\Entity\EntityTypeBundleInfoInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * Constructs a new OptionsShsWidget object.
   *
   * @param string $plugin_id
   *   Plugin id.
   * @param mixed $plugin_definition
   *   Plugin definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   Field definition.
   * @param array $settings
   *   Field settings.
   * @param array $third_party_settings
   *   Third party settings.
   * @param Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   Entity Type Manager for loading field details.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, EntityFieldManagerInterface $entity_field_manager, EntityTypeManagerInterface $entity_type_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info)
  {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);

    $this->entityFieldManager = $entity_field_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->groupableFields = ['boolean', "entity_reference"];
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
  }


  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
  {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('entity_field.manager'),
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info')
    );
  }


  /**
   * {@inheritdoc}
   */
  public static function defaultSettings()
  {
    return [
      'bundle_options' => [],
      'group_by' => '',
      'open_details' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state)
  {

    $elements = [];
    $options = $this->getGroupOptions($form_state);
    $selected = is_array($this->getSetting('group_by'))?$this->getSetting('group_by'):[$this->getSetting('group_by')];
    $maxGroupings = 3;

    // List of group by options.
    $elements['group_by'] = [
      '#type' => 'container',
    ];

    for ($i = 0; $i < $maxGroupings; $i++) {
      $elements['group_by'][$i] = [
        '#type' => 'select',
        '#title' => $this
          ->t('Group by'),
        '#options' => $this->getGroupOptions($form_state),
        '#default_value' => isset($selected[$i])?$selected[$i]:'',
      ];

      if ($i != 0) {

        $elements['group_by'][$i]['#description'] = $this->t('Nested field group optional.');
        $elements['group_by'][$i]['#states'] = [
          // Show this textfield if any radio except 'other' is selected.
          'visible' => [
            ':input[name="fields[field_signs][settings_edit_form][settings][group_by][' . ($i - 1) . ']"]' => ['!value' => null],
          ],
        ];
      }

      // Require field if younger sibling is selected.
      $a = $i;
      while ($a < $maxGroupings) {

        $requiredState = [];
        if ($a != $i) {
          $requiredState[$a] = 'or';
        } else {
          $elements['group_by'][$i]['#states']['required'] = [];
        }
        $requiredState[':input[name="fields[field_signs][settings_edit_form][settings][group_by][' . ($a + 1) . ']"]'] = ['!value' => null];
        $elements['group_by'][$i]['#states']['required'] += $requiredState;

        if ($a !== 0) {
          // $elements['group_by'][$i]['#states']['visible'] += $requiredState;
        }

        $a++;
      }
    }

    // If unsing views handler, have user slect options.
    if ($this->fieldDefinition->getSetting('handler') == "views") {
      $entity_type = $this->fieldDefinition->getSetting('target_type');
      $bundle_list = $this->entityTypeBundleInfo->getBundleInfo($entity_type);

      // Create unique wrapper id
      $id = Html::getId($this->fieldDefinition->getName()) . '-field-widget-display-settings-ajax-wrapper-' . md5($this->fieldDefinition->getUniqueIdentifier());

      // list of allowed bundles that will populate group_by field.
      $elements['bundle_options'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Which Bundles'),
        '#options' => array_map(function ($n) {
          return $n['label'];
        }, $bundle_list),
        '#default_value' => $this->getSetting('bundle_options'),
        '#ajax' => [
          'callback' => [$this, 'groupByAjaxCallback'], //alternative notation
          'disable-refocus' => FALSE,
          'event' => 'change',
          'wrapper' =>  $id, // This element is updated with this AJAX callback.
          'progress' => [
            'type' => 'throbber',
            'message' => $this->t('Verifying entry...'),
          ],
        ],
        '#weight' => 0,
        '#description' => $this->t('Select which bundles you wish to group by. Note you should only select the bundle listed in ')
      ];

      // Updated froup_by elements.
      $elements['group_by'] += [
        '#prefix' => "<div id='$id'>",
        '#suffix' => '</div>',
        '#weight' => 1,
      ];
    }

    $elements['open_details'] = [
      '#type' => 'checkbox',
      '#title' => $this
        ->t('Open details by default.'),
      '#weight' => 10,
      '#default_value' => $this->getSetting('open_details'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary()
  {
    $summary = [];

    $summary[] = $this->t('Group By: @list', ['@list' => is_array($this->getSetting('group_by')) ? join(", ", array_filter($this->getSetting('group_by'))) : $this->getSetting('group_by')]);

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state)
  {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    // Instantiate settings and selected options.
    $settings = $this->fieldDefinition->getSettings();
    $options = $this->getOptions($items->getEntity());
    $selected = $this->getSelectedOptions($items);

    // Parent deails field
    $element += array(
      '#type' => 'details',
      '#open' => TRUE,
    );

    // load entities from options.
    $option_entitities = $this->entityTypeManager->getStorage($settings['target_type'])->loadMultiple(array_keys($options));

    // Build grouping details and elements.
    foreach ($options as $options_key => $optionLabel) {
      $this->groupFormElements($element, $option_entitities[$options_key], $selected, $options_key, $optionLabel);
    }

    return $element;
  }


  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state)
  {
    // Initiate response.
    $massaged_values = [];
    // Get user values
    $input_values = $form_state->getUserInput();

    if ($this->fieldDefinition->getFieldStorageDefinition()->getCardinality() !== 1) {
      // Get the ids of each of the selected options and build
      foreach (array_keys(array_filter($this->flattenFormValues($input_values[$this->fieldDefinition->getName()]))) as $value) {
        $massaged_values[] = ['target_id' => $value];
      }
    } else {
      $massaged_values[] = ['target_id' => $input_values[$this->fieldDefinition->getName()]];
    }

    return $massaged_values;
  }

  /**
   * Ajax return to reload form
   *
   * @param array $form
   * @param FormStateInterface $form_state
   * @return void
   */
  public static function groupByAjaxCallback(array &$form, FormStateInterface $form_state)
  {
    $array_parents = $form_state->getTriggeringElement()['#array_parents'];
    $up_two_levels = array_slice($array_parents, 0, count($array_parents) - 2);
    $settings_path = array_merge($up_two_levels, ['group_by']);
    $settingsElement = NestedArray::getValue($form, $settings_path);

    return $settingsElement;
  }

  /**
   * Recursive array to flatten user input array.
   *
   * @param array $form_values
   *   Nested list of field input.
   * @return array
   *   Flattend list of field inputs.
   */
  protected function flattenFormValues($form_values)
  {
    $flattened_array = [];

    foreach ($form_values as $key => $value) {
      if (is_array($value)) {
        $flattened_array += $this->flattenFormValues($value);
      } else {
        $flattened_array[$key] = $value;
      }
    }

    return $flattened_array;
  }

  /**
   * Returns list of groupable field types.
   *
   * @return array
   */
  protected function getGroupOptions(FormStateInterface $form_state)
  {
    // Prepare response variable.
    $list = ['Select field'];

    // Get entity type and bundle options.
    $settings = $this->fieldDefinition->getSettings();

    // If using a entity reference views for options update settings.
    if ($this->fieldDefinition->getSetting('handler') == "views") {
      $bundle_options = $this->getSetting('bundle_options');
      $form_values = $form_state->getValues();

      // If form state date exists from ajax calls overrride $bundle_options.
      if (isset($form_values['fields'][$this->fieldDefinition->getName()]['settings_edit_form']['settings']['bundle_options'])) {
        $bundle_options = $form_values['fields'][$this->fieldDefinition->getName()]['settings_edit_form']['settings']['bundle_options'];
      }

      $settings['handler_settings']['target_bundles'] = array_keys(array_filter($bundle_options));
    }

    // for each bundle option build render elements.
    if (isset($settings['handler_settings']['target_bundles'])) {
      foreach ($settings['handler_settings']['target_bundles'] as $target_bundle) {
        $list = $list + $this->getFieldTree($settings['target_type'], $target_bundle);
      }
    }

    return $list;
  }

  /**
   * Recursive function to find all avaiable group types.
   *
   * @param string $entity_type
   *   Machine name of entity type.
   * @param string $bundle
   *   Bundle name.
   * @param array $field_list
   *   List of all the groupable fields.
   * @param array $field_path
   *   The key and label for groupable fields
   * @return array
   *   List of groupable fields.
   */
  protected function getFieldTree($entity_type, $bundle, &$field_list = [], $field_path = [])
  {
    $list = [];

    // Get custom fields.
    $unique_fields_definitions = array_diff_key($this->entityFieldManager->getFieldDefinitions($entity_type, $bundle), $this->entityFieldManager->getFieldDefinitions($entity_type, null));

    // loop though fields to finds all available fields.
    foreach ($unique_fields_definitions as $key => $field_definitions) {
      $field_definition_name = $entity_type . "." . $key;

      // If new field determine if groupable and store in $field_list.
      if (!in_array($field_definition_name, array_keys($field_list))) {
        $field_list[$field_definition_name] = $this->isGroupable($field_definitions);
      }

      // If groupable add to list.
      if ($field_list[$field_definition_name]) {

        $field_def_path = [];
        // Get key and value names.
        if (empty($field_path)) {
          $field_def_path['field_key'] = $key;
          $field_def_path['field_label'] = $field_definitions->getLabel();
        } else {
          $field_def_path['field_key']  = $field_path['field_key'] . "." . $key;
          $field_def_path['field_label'] = $field_path['field_label'] . " => " . $field_definitions->getLabel();
        }


        $list[$field_def_path['field_key']] = $field_def_path['field_label'];

        // Add nested fields
        if ($field_definitions->getType() == 'entity_reference') {
          $sub_field_settings = $field_definitions->getSettings();

          if (isset($sub_field_settings['handler_settings']['target_bundles'])) {
            foreach ($sub_field_settings['handler_settings']['target_bundles'] as $sub_bundle) {
              $list = $list + $this->getFieldTree($sub_field_settings['target_type'], $sub_bundle, $field_list, $field_def_path);
            }
          }
        }
      }
    }
    return $list;
  }

  /**
   * Determines if field is groupable.
   *
   * @param FieldDefinitionInterface $field
   * @return boolean
   */
  protected function isGroupable(FieldDefinitionInterface $field)
  {
    // Only fields with a single option is allowed
    if ($field->getFieldStorageDefinition()->getCardinality() !== 1) {
      return false;
    } else if (!in_array($field->getType(), $this->groupableFields)) {
      return false;
    }

    return true;
  }

  /**
   * Recursive function that modifies form inputs to grouped format.
   *
   * @param [type] $element
   *   Render element.
   * @param ContentEntityInterface $entity
   *   Loaded entity.
   * @param [type] $selected
   *   List of selected inputs.
   * @param [type] $options_key
   *   The key of current input being worked on.
   * @param [type] $optionLabel
   *   The label of current input being worked on.
   * @param integer $depth
   *   The depth of the recursive function.
   * @return void
   */
  protected function groupFormElements(&$element, ContentEntityInterface $entity, $selected, $options_key, $optionLabel, $depth = 0)
  {
    // Put in array for eventual nested grouping
    $group_by_list = is_array($this->getSetting('group_by')) ? $this->getSetting('group_by') : [$this->getSetting('group_by')];

    if ($depth == 0 && empty($group_by_list[$depth])) {
      $element['#description'] = "ALERT!! Missing 'Group by' selection on entity field widget";
    }


    if (count($group_by_list) != $depth && !empty($group_by_list[$depth])) {
      $group_details = $this->parseGroupDetails(explode('.', $group_by_list[$depth]), $entity);
      $group_label = 'group_' . $group_details['key'];

      // Created group.
      if (!isset($element[$group_label])) {
        $element[$group_label] = array(
          '#type' => 'details',
          '#title' => $group_details['label'],
          '#open' => $this->getSetting('open_details'),
        );
      }

      $this->groupFormElements($element[$group_label], $entity, $selected, $options_key, $optionLabel, ++$depth);
    } else {

      // Common settings between checkbox and radio buttons;
      $element[$options_key] = array(
        '#title' => $optionLabel,
        '#default_value' => in_array($options_key, $selected) ? 1 : 0,
      );

      // Modify elements based on if mutiple options can be slected.
      if ($this->multiple) {
        $element[$options_key] += ['#type' => 'checkbox'];
      } else {
        $element[$options_key] += array(
          '#type' => 'radio',
          '#parents' => array($this->fieldDefinition->getName()),
          '#return_value' => $options_key,
          '#attributes' => in_array($options_key, $selected) ? ['checked' => "checked"] : [],
        );
      }
    }
  }

  /**
   * A recursive function that gets the group key and label.
   *
   * @param [type] $field_chain
   *   Array of field nesting we need to group by.
   * @param ContentEntityInterface $entity
   *   The Entity being parsed.
   * @param integer $depth
   *   The depth of the recursive function.
   * @return array
   *   The key and label of the groupable wrapper.
   */
  protected function parseGroupDetails($field_chain, ContentEntityInterface $entity, $depth = 0)
  {
    $details = [
      'key' => 'na',
      'label' => 'No Value',
    ];

    if (count($field_chain) != $depth) {
      $field_list = $this->entityFieldManager->getFieldDefinitions($entity->getEntityTypeId(), $entity->bundle());

      if (!empty($field_chain[$depth]) && $entity->hasField($field_chain[$depth])) {
        $value = $entity->get($field_chain[$depth])->getValue();
        $settings =  $field_list[$field_chain[$depth]]->getSettings();

        if (isset($value[0]['target_id'])) {
          $entity = $this->entityTypeManager->getStorage($settings['target_type'])->load($value[0]['target_id']);
          $details = $this->parseGroupDetails($field_chain, $entity, ++$depth);
        }
      }
    } else {
      $details = [
        'key' => $entity->id(),
        'label' => $entity->label(),
      ];
    }

    return $details;
  }
}
