<?php

namespace App\Filament\Resources;

use App\Enums\SignalDirection;
use App\Enums\SignalStatus;
use App\Filament\Resources\SignalResource\Pages;
use App\Models\Signal;
use App\Models\SignalCategory;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SignalResource extends Resource
{
    protected static ?string $model = Signal::class;

    protected static ?string $navigationIcon = 'heroicon-o-signal';

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.groups.signals');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.resources.signals');
    }

    public static function getModelLabel(): string
    {
        return __('admin.resources.signal.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.resources.signal.plural');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('signal_category_id')
                    ->label(__('admin.resources.signal.fields.category'))
                    ->options(fn () => SignalCategory::query()->orderBy('sort_order')->pluck('name', 'id'))
                    ->searchable(),
                TextInput::make('strategy_key')
                    ->label(__('admin.resources.signal.fields.strategy_key'))
                    ->required()
                    ->maxLength(64)
                    ->default('smart_volume_v1'),
                TextInput::make('exchange')
                    ->label(__('admin.resources.signal.fields.exchange'))
                    ->required()
                    ->maxLength(32)
                    ->default(config('market_data.default_provider', 'bybit')),
                TextInput::make('symbol')
                    ->label(__('admin.resources.signal.fields.symbol'))
                    ->required()
                    ->maxLength(32),
                TextInput::make('timeframe')
                    ->label(__('admin.resources.signal.fields.timeframe'))
                    ->required()
                    ->maxLength(16)
                    ->default('1h'),
                Select::make('direction')
                    ->label(__('admin.resources.signal.fields.direction'))
                    ->required()
                    ->options(self::directionOptions()),
                DateTimePicker::make('entry_at')
                    ->label(__('admin.resources.signal.fields.entry_at')),
                TextInput::make('entry_price')
                    ->label(__('admin.resources.signal.fields.entry_price'))
                    ->required()
                    ->numeric(),
                TextInput::make('stop_loss')
                    ->label(__('admin.resources.signal.fields.stop_loss'))
                    ->required()
                    ->numeric(),
                TextInput::make('take_profit')
                    ->label(__('admin.resources.signal.fields.take_profit'))
                    ->required()
                    ->numeric(),
                TextInput::make('reward_risk')
                    ->label(__('admin.resources.signal.fields.reward_risk'))
                    ->numeric(),
                Select::make('status')
                    ->label(__('admin.resources.signal.fields.status'))
                    ->required()
                    ->options(self::statusOptions())
                    ->default('draft'),
                DateTimePicker::make('published_at')
                    ->label(__('admin.resources.signal.fields.published_at')),
                DateTimePicker::make('expires_at')
                    ->label(__('admin.resources.signal.fields.expires_at')),
                Textarea::make('notes')
                    ->label(__('admin.resources.signal.fields.notes'))
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('symbol')
                    ->label(__('admin.resources.signal.fields.symbol'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('timeframe')
                    ->label(__('admin.resources.signal.fields.timeframe'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('direction')
                    ->label(__('admin.resources.signal.fields.direction'))
                    ->badge()
                    ->formatStateUsing(function (mixed $state): string {
                        $value = $state instanceof SignalDirection ? $state->value : (string) $state;

                        return self::directionOptions()[$value] ?? $value;
                    })
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('admin.resources.signal.fields.status'))
                    ->badge()
                    ->formatStateUsing(function (mixed $state): string {
                        $value = $state instanceof SignalStatus ? $state->value : (string) $state;

                        return self::statusOptions()[$value] ?? $value;
                    })
                    ->sortable(),
                TextColumn::make('entry_at')
                    ->label(__('admin.resources.signal.fields.entry_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('published_at')
                    ->label(__('admin.resources.signal.fields.published_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('expires_at')
                    ->label(__('admin.resources.signal.fields.expires_at'))
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('entry_price')
                    ->label(__('admin.resources.signal.fields.entry_price'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('stop_loss')
                    ->label(__('admin.resources.signal.fields.stop_loss'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('take_profit')
                    ->label(__('admin.resources.signal.fields.take_profit'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('reward_risk')
                    ->label(__('admin.resources.signal.fields.reward_risk'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
            ])
            ->actions([
                \Filament\Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                \Filament\Tables\Actions\BulkActionGroup::make([
                    \Filament\Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\SignalResource\RelationManagers\SignalUpdatesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSignals::route('/'),
            'create' => Pages\CreateSignal::route('/create'),
            'edit' => Pages\EditSignal::route('/{record}/edit'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function directionOptions(): array
    {
        return [
            'LONG' => __('admin.resources.signal.options.direction.long'),
            'SHORT' => __('admin.resources.signal.options.direction.short'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function statusOptions(): array
    {
        return [
            'draft' => __('admin.resources.signal.options.status.draft'),
            'published' => __('admin.resources.signal.options.status.published'),
            'closed' => __('admin.resources.signal.options.status.closed'),
            'archived' => __('admin.resources.signal.options.status.archived'),
        ];
    }
}
