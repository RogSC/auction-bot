<?php

declare(strict_types=1);

namespace App\Filament\Resources\Releases\Pages;

use App\Filament\Resources\Releases\ReleaseResource;
use App\Models\Release;
use App\Modules\Release\Application\CancelRelease;
use App\Modules\Release\Application\ReleaseOperationException;
use App\Modules\Release\Application\ScheduleRelease;
use App\Modules\Release\Application\StartRelease;
use App\Modules\Release\Domain\Enums\ReleaseStatus;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditRelease extends EditRecord
{
    protected static string $resource = ReleaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('schedule')
                ->label('Schedule release')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->getRecord()->status === ReleaseStatus::Draft)
                ->action(fn (): mixed => $this->performOperation(fn (): Release => app(ScheduleRelease::class)->handle($this->getRecord()->id))),
            Action::make('startNow')
                ->label('Start now')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->getRecord()->status === ReleaseStatus::Scheduled)
                ->action(fn (): mixed => $this->performOperation(fn (): Release => app(StartRelease::class)->handle($this->getRecord()->id, startNow: true))),
            Action::make('cancel')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (): bool => in_array($this->getRecord()->status, [ReleaseStatus::Draft, ReleaseStatus::Scheduled, ReleaseStatus::Running], true))
                ->action(fn (): mixed => $this->performOperation(fn (): Release => app(CancelRelease::class)->handle($this->getRecord()->id))),
            DeleteAction::make()->visible(fn (): bool => $this->getRecord()->status === ReleaseStatus::Draft),
        ];
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Release $record */
        if ($record->status !== ReleaseStatus::Draft) {
            throw ValidationException::withMessages([
                'title' => 'Only a draft release calendar can be changed. Create a new draft to revise a scheduled release.',
            ]);
        }

        $record->update($data);

        return $record;
    }

    /** @param callable(): Release $operation */
    private function performOperation(callable $operation): mixed
    {
        try {
            $release = $operation();
        } catch (ReleaseOperationException $exception) {
            throw ValidationException::withMessages(['title' => $exception->getMessage()]);
        }

        return $this->redirect(ReleaseResource::getUrl('edit', ['record' => $release]));
    }
}
