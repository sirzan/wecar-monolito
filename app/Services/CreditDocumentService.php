<?php

namespace App\Services;

use App\Models\CreditApplication;
use App\Models\CreditDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CreditDocumentService
{
    private const DISK = 'local';

    public function store(User $user, string $slot, UploadedFile $file, ?CreditApplication $application = null): CreditDocument
    {
        if ($file->getClientMimeType() !== 'application/pdf' || $file->getSize() > 5 * 1024 * 1024) {
            throw ValidationException::withMessages([
                $slot => 'Solo se aceptan PDF de máximo 5 MB.',
            ]);
        }

        $userPath = "credit-docs/users/{$user->getKey()}/{$slot}.pdf";
        Storage::disk(self::DISK)->putFileAs(dirname($userPath), $file, basename($userPath));

        $path = $userPath;

        if ($application) {
            $applicationPath = "credit-docs/applications/{$application->getKey()}/{$slot}.pdf";
            Storage::disk(self::DISK)->copy($userPath, $applicationPath);
            $path = $applicationPath;
        }

        return CreditDocument::query()->create([
            'user_id' => $user->getKey(),
            'credit_application_id' => $application?->getKey(),
            'slot' => $slot,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => 'application/pdf',
            'size' => $file->getSize(),
        ]);
    }

    public function reuse(User $user, string $slot, CreditApplication $application): CreditDocument
    {
        $source = $this->latestForUser($user, $slot);

        if (! $source || ! Storage::disk(self::DISK)->exists($source->path)) {
            throw ValidationException::withMessages([
                $slot => 'No se encontró un documento guardado para reutilizar.',
            ]);
        }

        $applicationPath = "credit-docs/applications/{$application->getKey()}/{$slot}.pdf";
        Storage::disk(self::DISK)->copy($source->path, $applicationPath);

        return CreditDocument::query()->create([
            'user_id' => $user->getKey(),
            'credit_application_id' => $application->getKey(),
            'slot' => $slot,
            'path' => $applicationPath,
            'original_name' => $source->original_name,
            'mime' => 'application/pdf',
            'size' => $source->size,
        ]);
    }

    public function latestForUser(User $user, string $slot): ?CreditDocument
    {
        return CreditDocument::query()
            ->where('user_id', $user->getKey())
            ->where('slot', $slot)
            ->latest()
            ->first();
    }

    /**
     * @return array<int, array{slot: string, name: string|null, uploaded_at: string|null}>
     */
    public function userDocuments(User $user): array
    {
        return collect(['ine', 'domicilio', 'estado_cuenta'])
            ->map(function (string $slot) use ($user) {
                $document = $this->latestForUser($user, $slot);

                return [
                    'slot' => $slot,
                    'name' => $document?->original_name,
                    'uploaded_at' => $document?->created_at?->toIso8601String(),
                    'available' => $document !== null && Storage::disk(self::DISK)->exists($document->path),
                ];
            })
            ->values()
            ->all();
    }

    public function downloadPath(CreditDocument $document): string
    {
        $path = Storage::disk(self::DISK)->path($document->path);

        abort_unless(file_exists($path), 404);

        return $path;
    }
}
