<?php

namespace Drupal\ghi_blocks\Drush\Commands;

use Drupal\ghi_blocks\Services\FinancialLabelUpdater;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides one-time financial label cleanup commands.
 */
final class FinancialLabelCommands extends DrushCommands {

  /**
   * Constructs the command handler.
   */
  public function __construct(private readonly FinancialLabelUpdater $updater) {
    parent::__construct();
  }

  /**
   * Creates the command handler from the Drupal service container.
   */
  public static function create(ContainerInterface $container): self {
    return new self(FinancialLabelUpdater::create($container));
  }

  /**
   * Cleans up stored financial table label overrides in current layouts.
   */
  #[CLI\Command(name: 'ghi-blocks:cleanup-financial-labels')]
  #[CLI\Help(description: 'Removes redundant financial table label overrides and obsolete currency suffixes from current layouts. Run after locale translations have been imported.')]
  #[CLI\Usage(name: 'drush ghi-blocks:cleanup-financial-labels', description: 'Clean up current node and page-template layouts.')]
  public function cleanupFinancialLabels(): void {
    $references = $this->updater->getEntityReferences();
    $this->io()->progressStart(count($references));
    $results = $this->updater->updateEntityReferences($references, function (): void {
      $this->io()->progressAdvance();
    });
    $this->io()->progressFinish();

    $this->logger()->success(dt('Removed @removed redundant financial label overrides and stripped currency units from @stripped customized labels in @entities current layouts. Processed @processed references; @missing referenced entities were unavailable.', [
      '@removed' => $results['removed'],
      '@stripped' => $results['stripped'],
      '@entities' => $results['updated_entities'],
      '@processed' => $results['processed'],
      '@missing' => $results['missing'],
    ]));
  }

}
