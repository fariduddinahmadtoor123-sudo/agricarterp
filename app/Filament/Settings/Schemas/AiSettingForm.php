<?php

namespace App\Filament\Settings\Schemas;

use App\Services\Settings\AiSettingResolver;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AiSettingForm
{
    public static function configure(Schema $schema): Schema
    {
        $resolver = app(AiSettingResolver::class);

        return $schema
            ->columns(1)
            ->extraAttributes(['class' => 'agricart-ai-setting-form'])
            ->components([
                Section::make('OpenRouter Connection')
                    ->description('Paste your OpenRouter API key here. It is stored securely in your database and never shown again after saving.')
                    ->schema([
                        TextInput::make('openrouter_api_key')
                            ->label('OpenRouter API Key')
                            ->password()
                            ->revealable()
                            ->placeholder('sk-or-v1-...')
                            ->helperText(fn (): string => $resolver->hasApiKey()
                                ? 'A key is already saved. Leave blank to keep it, or paste a new key to replace it.'
                                : 'Get your key from openrouter.ai → Keys.')
                            ->dehydrated(fn (?string $state): bool => filled($state)),
                    ]),

                Section::make('Vision Model')
                    ->description('Choose the AI model used for image + text enrichment. Models with vision support work best.')
                    ->schema([
                        Select::make('vision_model')
                            ->label('Vision Model')
                            ->options($resolver->visionModelOptions())
                            ->required()
                            ->native(false)
                            ->searchable()
                            ->helperText('Models are loaded from OpenRouter when your API key is saved. Use search to find any available vision model.'),
                    ]),

                Section::make('Enrichment Behaviour')
                    ->schema([
                        Toggle::make('enrichment_enabled')
                            ->label('Enable AI enrichment')
                            ->default(true),
                        TextInput::make('batch_limit')
                            ->label('Records per run')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(500)
                            ->required()
                            ->helperText('How many products or categories are queued each time you click Run AI Enrichment.'),
                    ]),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultState(): array
    {
        return app(AiSettingResolver::class)->formState();
    }
}
