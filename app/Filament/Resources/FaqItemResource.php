<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FaqItemResource\Pages;
use App\Models\FaqItem;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FaqItemResource extends Resource
{
    protected static ?string $model = FaqItem::class;

    protected static ?string $navigationIcon = 'heroicon-o-question-mark-circle';

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.groups.content');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.resources.faq_items');
    }

    public static function getModelLabel(): string
    {
        return __('admin.resources.faq_item.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.resources.faq_item.plural');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Tabs::make('translations')
                ->label(__('admin.resources.faq_item.fields.translations'))
                ->tabs(self::translationTabs())
                ->columnSpanFull(),
            TextInput::make('sort_order')
                ->label(__('admin.resources.faq_item.fields.sort_order'))
                ->numeric()
                ->minValue(0)
                ->default(0)
                ->required(),
            Toggle::make('is_active')
                ->label(__('admin.resources.faq_item.fields.is_active'))
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('sort_order')
                    ->label(__('admin.resources.faq_item.fields.sort_order'))
                    ->sortable(),
                TextColumn::make('question_translations')
                    ->label(__('admin.resources.faq_item.fields.question'))
                    ->state(fn (FaqItem $record): string => $record->questionForLocale()),
                IconColumn::make('is_active')
                    ->label(__('admin.resources.faq_item.fields.is_active'))
                    ->boolean()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label(__('admin.resources.faq_item.fields.updated_at'))
                    ->since()
                    ->sortable()
                    ->toggleable(),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFaqItems::route('/'),
            'create' => Pages\CreateFaqItem::route('/create'),
            'edit' => Pages\EditFaqItem::route('/{record}/edit'),
        ];
    }

    /**
     * @return array<int, Tab>
     */
    private static function translationTabs(): array
    {
        $tabs = [];
        $locales = (array) config('locales.supported', ['en']);

        foreach ($locales as $locale) {
            $locale = (string) $locale;
            $tabs[] = Tab::make(strtoupper($locale))
                ->schema([
                    TextInput::make("question_translations.{$locale}")
                        ->label(__('admin.resources.faq_item.fields.question_for', ['locale' => strtoupper($locale)]))
                        ->required()
                        ->maxLength(500),
                    Textarea::make("answer_translations.{$locale}")
                        ->label(__('admin.resources.faq_item.fields.answer_for', ['locale' => strtoupper($locale)]))
                        ->required()
                        ->rows(5),
                ]);
        }

        return $tabs;
    }
}
