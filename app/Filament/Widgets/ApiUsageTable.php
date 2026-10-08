<?php

namespace App\Filament\Widgets;

use App\Models\ApiToken;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class ApiUsageTable extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 2;

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('admin.dashboard.api_usage.heading'))
            ->description(__('admin.dashboard.api_usage.description'))
            ->query($this->getTableQuery())
            ->defaultSort('last_used_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label(__('admin.dashboard.api_usage.columns.user'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.email')
                    ->label(__('admin.dashboard.api_usage.columns.email'))
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('name')
                    ->label(__('admin.dashboard.api_usage.columns.token'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('last_used_at')
                    ->label(__('admin.dashboard.api_usage.columns.last_used_at'))
                    ->dateTime()
                    ->since()
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_used_ip')
                    ->label(__('admin.dashboard.api_usage.columns.last_used_ip'))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('expires_at')
                    ->label(__('admin.dashboard.api_usage.columns.expires_at'))
                    ->dateTime()
                    ->since()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('admin.dashboard.api_usage.columns.active'))
                    ->boolean()
                    ->getStateUsing(fn (ApiToken $record): bool => $record->isActive()),
            ])
            ->filters([
                Tables\Filters\Filter::make('active_only')
                    ->label(__('admin.dashboard.api_usage.filters.active_only'))
                    ->query(function (Builder $query): Builder {
                        return $query
                            ->whereNull('revoked_at')
                            ->where(function (Builder $inner): Builder {
                                return $inner->whereNull('expires_at')
                                    ->orWhere('expires_at', '>', now());
                            });
                    }),
                Tables\Filters\Filter::make('used_30d')
                    ->label(__('admin.dashboard.api_usage.filters.used_30d'))
                    ->query(fn (Builder $query): Builder => $query->where('last_used_at', '>=', now()->subDays(30))),
            ])
            ->paginated([10, 25, 50]);
    }

    protected function getTableQuery(): Builder
    {
        return ApiToken::query()
            ->with('user')
            ->whereNotNull('last_used_at');
    }
}

