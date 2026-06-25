<?php

namespace App\Filament\ProductCatalog\Support;

use App\Services\Ai\CatalogEnrichmentQueueService;
use App\Support\Settings\AiSettingAuthorization;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Enums\IconSize;
use Filament\Support\Enums\Size;
use Filament\Support\Icons\Heroicon;
use InvalidArgumentException;

class RunCatalogAiEnrichmentAction
{
    /**
     * @return array<int, Action>
     */
    public static function headerActionsFor(string $target): array
    {
        $action = static::make($target);

        return $action !== null ? [$action] : [];
    }

    public static function make(string $target): ?Action
    {
        if (! AiSettingAuthorization::canRunEnrichment()) {
            return null;
        }

        $config = match ($target) {
            'products' => [
                'name' => 'runAiEnrichment',
                'entityPlural' => 'products',
                'entitySingular' => 'product',
                'includeCategories' => false,
                'includeProducts' => true,
            ],
            'categories' => [
                'name' => 'runCategoryAiEnrichment',
                'entityPlural' => 'categories',
                'entitySingular' => 'category',
                'includeCategories' => true,
                'includeProducts' => false,
            ],
            default => throw new InvalidArgumentException("Unknown AI enrichment target [{$target}]."),
        };

        return Action::make($config['name'])
            ->label('AI Enrich')
            ->icon(Heroicon::OutlinedSparkles)
            ->iconSize(IconSize::Small)
            ->size(Size::Small)
            ->button()
            ->extraAttributes([
                'class' => 'agricart-catalog-ai-btn',
                'title' => 'Run AI enrichment for empty ' . $config['entityPlural'],
            ])
            ->requiresConfirmation()
            ->modalHeading('Run AI Enrichment')
            ->modalDescription(
                'Empty fields on pending ' . $config['entityPlural'] . ' will be sent to AI in the background. '
                . 'You can keep using the ERP while enrichment runs. Failed items are skipped and the rest continue.',
            )
            ->modalSubmitActionLabel('Start enrichment')
            ->action(function (CatalogEnrichmentQueueService $queueService) use ($config): void {
                try {
                    $queued = $queueService->dispatchPendingAndProcess(
                        includeCategories: $config['includeCategories'],
                        includeProducts: $config['includeProducts'],
                    );
                } catch (\Throwable $exception) {
                    Notification::make()
                        ->danger()
                        ->title($exception->getMessage())
                        ->send();

                    return;
                }

                $total = $config['includeProducts']
                    ? $queued['products']
                    : $queued['categories'];

                if ($total === 0) {
                    Notification::make()
                        ->info()
                        ->title('No ' . $config['entityPlural'] . ' need enrichment right now')
                        ->send();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title("AI enrichment started for {$total} " . $config['entityPlural'])
                    ->body('Processing continues in the background. Check AI Status on each ' . $config['entitySingular'] . ' later.')
                    ->send();
            });
    }
}
