<?php

declare(strict_types=1);

namespace Drupal\canvas_bootstrap\Hook;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Extension\ExtensionPathResolver;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\canvas_bootstrap\ComponentSource\ThemeAwareSingleDirectoryComponentDiscovery;
use Symfony\Component\Yaml\Yaml;

/**
 * Form hook implementations for canvas_bootstrap.
 */
class CanvasBootstrapFormHooks {
  use StringTranslationTrait;

  private const COMPONENT_TYPE_PREFIX = 'sdc';
  private const GROUP_CONTAINER_KEY = 'canvas_bootstrap_groups';

  public function __construct(
    private readonly ExtensionPathResolver $pathResolver,
  ) {}

  /**
   * Implements hook_form_FORM_ID_alter() for component instance forms.
   */
  #[Hook('form_component_instance_form_alter')]
  public function formComponentInstanceFormAlter(array &$form, FormStateInterface $form_state): void {
    $form['#attached']['library'][] = 'canvas_bootstrap/component_instance_form';

    $tree_value = $form['form_canvas_tree']['#value'] ?? NULL;
    if (!is_string($tree_value) || $tree_value === '') {
      return;
    }

    $tree = Json::decode($tree_value);
    if (!is_array($tree) || empty($tree['type']) || !is_string($tree['type'])) {
      return;
    }

    [$component_id] = explode('@', $tree['type'], 2);
    if (!isset($form['canvas_component_props']) || !is_array($form['canvas_component_props'])) {
      return;
    }

    $instance_key = array_key_first($form['canvas_component_props']);
    if ($instance_key === NULL) {
      return;
    }

    $props =& $form['canvas_component_props'][$instance_key];
    if (!is_array($props)) {
      return;
    }

    $groups = $this->getUiGroups($component_id);
    if ($groups) {
      $this->applyUiGroups($props, $groups);
    }
  }

  /**
   * Implements hook_canvas_component_source_alter().
   */
  #[Hook('canvas_component_source_alter')]
  public function canvasComponentSourceAlter(array &$definitions): void {
    if (isset($definitions['sdc'])) {
      $definitions['sdc']['discovery'] = ThemeAwareSingleDirectoryComponentDiscovery::class;
    }
  }

  /**
   * Loads UI group definitions from the component metadata.
   */
  private function getUiGroups(string $component_id): array {
    $parts = explode('.', $component_id, 3);
    if (count($parts) !== 3 || $parts[0] !== self::COMPONENT_TYPE_PREFIX) {
      return [];
    }

    $provider = $parts[1];
    $component_name = $parts[2];
    $component_path = $this->resolveComponentMetadataPath($provider, $component_name);
    if ($component_path === NULL) {
      return [];
    }

    $metadata = Yaml::parseFile($component_path);
    if (!is_array($metadata)) {
      return [];
    }

    $groups = $metadata['canvas_bootstrap']['ui_groups'] ?? [];
    return is_array($groups) ? $groups : [];
  }

  /**
   * Resolve the component metadata path for a module or theme provider.
   */
  private function resolveComponentMetadataPath(string $provider, string $component_name): ?string {
    $base_paths = [];
    try {
      $theme_path = $this->pathResolver->getPath('theme', $provider);
      if (is_string($theme_path) && $theme_path !== '') {
        $base_paths[] = $theme_path;
      }
    }
    catch (\InvalidArgumentException) {
      // Provider is not a theme.
    }

    try {
      $module_path = $this->pathResolver->getPath('module', $provider);
      if (is_string($module_path) && $module_path !== '') {
        $base_paths[] = $module_path;
      }
    }
    catch (\InvalidArgumentException) {
      // Provider is not a module.
    }

    foreach ($base_paths as $base_path) {
      $component_path = $base_path . '/components/' . $component_name . '/' . $component_name . '.component.yml';
      if (is_file($component_path)) {
        return $component_path;
      }
    }

    return NULL;
  }

  /**
   * Applies UI groups to the component instance form.
   */
  private function applyUiGroups(array &$props, array $groups): void {
    $group_container = self::GROUP_CONTAINER_KEY;
    $props[$group_container] = [
      '#type' => 'vertical_tabs',
      '#weight' => 35,
    ];

    $weight = 0;
    $moved_any = FALSE;
    foreach ($groups as $group_label => $group_definition) {
      if (!is_array($group_definition)) {
        continue;
      }

      $group_weight = $this->groupWeight($group_definition);
      $group_fields = $this->groupFields($group_definition);
      $group_subgroups = $this->groupSubgroups($group_definition);

      $group_key = $this->groupKey($group_label);
      $props[$group_key] = [
        '#type' => 'details',
        '#title' => $this->t('@label', ['@label' => (string) $group_label]),
        '#open' => FALSE,
        '#group' => $group_container,
        '#weight' => $group_weight ?? $weight,
        '#attributes' => ['class' => ['canvas-bootstrap-details', 'setting-group']],
      ];

      if ($group_fields !== NULL) {
        foreach ($group_fields as $field) {
          if (isset($props[$field])) {
            $props[$group_key][$field] = $props[$field];
            unset($props[$field]);
            $moved_any = TRUE;
          }
        }
      }
      else {
        $sub_weight = 0;
        foreach ($group_subgroups ?? [] as $sub_label => $sub_definition) {
          if (!is_array($sub_definition)) {
            continue;
          }
          $sub_key = $this->groupKey($group_label . '_' . $sub_label);
          $subgroup_weight = $this->groupWeight($sub_definition);
          $subgroup_fields = $this->groupFields($sub_definition) ?? $sub_definition;
          $props[$group_key][$sub_key] = [
            '#type' => 'fieldset',
            '#title' => $this->t('@label', ['@label' => (string) $sub_label]),
            '#weight' => $subgroup_weight ?? $sub_weight,
          ];
          foreach ($subgroup_fields as $field) {
            if (isset($props[$field])) {
              $props[$group_key][$sub_key][$field] = $props[$field];
              unset($props[$field]);
              $moved_any = TRUE;
            }
          }
          $sub_weight++;
        }
      }
      $weight++;
    }

    if (!$moved_any) {
      unset($props[$group_container]);
    }
  }

  /**
   * Builds a stable key for UI group containers.
   */
  private function groupKey(string $label): string {
    $key = strtolower(preg_replace('/[^a-z0-9]+/', '_', $label));
    return 'canvas_bootstrap_group_' . trim($key, '_');
  }

  /**
   * Extracts the weight from a UI group definition.
   */
  private function groupWeight(array $definition): ?int {
    if (isset($definition['weight']) && is_numeric($definition['weight'])) {
      return (int) $definition['weight'];
    }
    return NULL;
  }

  /**
   * Extracts the fields from a UI group definition.
   */
  private function groupFields(array $definition): ?array {
    if (isset($definition['fields']) && is_array($definition['fields'])) {
      return $definition['fields'];
    }
    if (array_is_list($definition)) {
      return $definition;
    }
    return NULL;
  }

  /**
   * Extracts subgroups from a UI group definition.
   */
  private function groupSubgroups(array $definition): ?array {
    if (isset($definition['groups']) && is_array($definition['groups'])) {
      return $definition['groups'];
    }
    $is_mapping = !array_is_list($definition);
    if ($is_mapping && !isset($definition['fields'])) {
      return $definition;
    }
    return NULL;
  }

}
