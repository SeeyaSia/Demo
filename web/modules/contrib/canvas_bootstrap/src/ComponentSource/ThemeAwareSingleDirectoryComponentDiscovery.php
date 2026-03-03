<?php

declare(strict_types=1);

namespace Drupal\canvas_bootstrap\ComponentSource;

use Drupal\canvas\ComponentSource\ComponentCandidatesDiscoveryInterface;
use Drupal\canvas\ComponentDoesNotMeetRequirementsException;
use Drupal\canvas\Plugin\Canvas\ComponentSource\SingleDirectoryComponentDiscovery;
use Drupal\Core\Theme\ComponentPluginManager;
use Drupal\Core\Theme\ExtensionType;
use Drupal\Core\Theme\ThemeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Prefer active theme SDCs over module SDCs when names collide.
 */
final class ThemeAwareSingleDirectoryComponentDiscovery implements ComponentCandidatesDiscoveryInterface {

  /**
   * Inner SDC discovery helper.
   *
   * @var \Drupal\canvas\Plugin\Canvas\ComponentSource\SingleDirectoryComponentDiscovery
   */
  private SingleDirectoryComponentDiscovery $inner;

  /**
   * Preferred theme component plugin IDs by machine name.
   *
   * @var array<string, string>|null
   */
  private ?array $preferredThemeComponentIds = NULL;

  public function __construct(
    private readonly ComponentPluginManager $componentPluginManager,
    private readonly ThemeManagerInterface $themeManager,
  ) {
    $this->inner = new SingleDirectoryComponentDiscovery($this->componentPluginManager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get(ComponentPluginManager::class),
      $container->get(ThemeManagerInterface::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function discover(): array {
    return $this->inner->discover();
  }

  /**
   * {@inheritdoc}
   */
  public function checkRequirements(string $source_specific_id): void {
    if ($this->isOverriddenByActiveTheme($source_specific_id)) {
      throw new ComponentDoesNotMeetRequirementsException([
        'Overridden by an active theme component with the same machine name.',
      ]);
    }
    $this->inner->checkRequirements($source_specific_id);
  }

  /**
   * {@inheritdoc}
   */
  public function computeComponentSettings(string $source_specific_id): array {
    return $this->inner->computeComponentSettings($source_specific_id);
  }

  /**
   * {@inheritdoc}
   */
  public function computeInitialComponentProvider(string $source_specific_id): ?string {
    return $this->inner->computeInitialComponentProvider($source_specific_id);
  }

  /**
   * {@inheritdoc}
   */
  public function computeInitialComponentStatus(string $source_specific_id): bool {
    return $this->inner->computeInitialComponentStatus($source_specific_id);
  }

  /**
   * {@inheritdoc}
   */
  public function computeCurrentComponentMetadata(string $source_specific_id): array {
    return $this->inner->computeCurrentComponentMetadata($source_specific_id);
  }

  /**
   * {@inheritdoc}
   */
  public static function getComponentConfigEntityId(string $source_specific_component_id): string {
    return SingleDirectoryComponentDiscovery::getComponentConfigEntityId($source_specific_component_id);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSourceSpecificComponentId(string $component_id): string {
    return SingleDirectoryComponentDiscovery::getSourceSpecificComponentId($component_id);
  }

  /**
   * Checks if a module component is overridden by the active theme.
   *
   * @param string $source_specific_id
   *   The source-specific component ID.
   *
   * @return bool
   *   TRUE when a theme component with the same machine name is preferred.
   */
  private function isOverriddenByActiveTheme(string $source_specific_id): bool {
    $definition = $this->componentPluginManager->getDefinition($source_specific_id);
    if (($definition['extension_type'] ?? NULL) !== ExtensionType::Module) {
      return FALSE;
    }

    $preferred = $this->getPreferredThemeComponents();
    $machine_name = $definition['machineName'] ?? $source_specific_id;
    return isset($preferred[$machine_name]) && $preferred[$machine_name] !== $source_specific_id;
  }

  /**
   * Build a lookup of preferred theme component IDs by machine name.
   *
   * @return array<string, string>
   *   Machine name => preferred theme component plugin ID.
   */
  private function getPreferredThemeComponents(): array {
    if ($this->preferredThemeComponentIds !== NULL) {
      return $this->preferredThemeComponentIds;
    }

    $active_theme = $this->themeManager->getActiveTheme();
    if ($active_theme === NULL) {
      $this->preferredThemeComponentIds = [];
      return $this->preferredThemeComponentIds;
    }

    $theme_names = [$active_theme->getName()];
    foreach ($active_theme->getBaseThemeExtensions() as $extension) {
      $theme_names[] = $extension->getName();
    }
    $theme_weights = array_flip($theme_names);

    $definitions = $this->inner->discover();
    $grouped = [];
    foreach ($definitions as $plugin_id => $definition) {
      $machine_name = $definition['machineName'] ?? $plugin_id;
      $grouped[$machine_name][$plugin_id] = $definition;
    }

    $preferred = [];
    foreach ($grouped as $machine_name => $candidates) {
      $theme_candidates = array_filter(
        $candidates,
        static function (array $definition) use ($theme_names): bool {
          if (($definition['extension_type'] ?? NULL) !== ExtensionType::Theme) {
            return FALSE;
          }
          $provider = $definition['provider'] ?? '';
          return in_array($provider, $theme_names, TRUE);
        }
      );

      if (empty($theme_candidates)) {
        continue;
      }

      uasort(
        $theme_candidates,
        static function (array $a, array $b) use ($theme_weights): int {
          $weight_a = $theme_weights[$a['provider']] ?? PHP_INT_MAX;
          $weight_b = $theme_weights[$b['provider']] ?? PHP_INT_MAX;
          return $weight_a <=> $weight_b;
        }
      );

      $chosen_id = array_key_first($theme_candidates);
      if ($chosen_id !== NULL) {
        $preferred[$machine_name] = $chosen_id;
      }
    }

    $this->preferredThemeComponentIds = $preferred;
    return $this->preferredThemeComponentIds;
  }

}
