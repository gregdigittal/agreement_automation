<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\ExchangeRoom;
use App\Models\ExchangeRoomPost;
use App\Models\User;
use App\Models\VendorUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ExchangeRoomService
{
    private const ALLOWED_MIMES = ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];

    private const STAGE_ORDER = ['draft_round', 'vendor_review', 'revised', 'final'];

    public function getOrCreate(Contract $contract, ?string $createdBy = null): ExchangeRoom
    {
        return ExchangeRoom::firstOrCreate(
            ['contract_id' => $contract->id],
            [
                'status' => 'open',
                'negotiation_stage' => 'draft_round',
                'created_by' => $createdBy ?? auth()->id(),
            ]
        );
    }

    public function post(
        ExchangeRoom $room,
        User|VendorUser $author,
        string $actorSide,
        ?string $message = null,
        ?UploadedFile $file = null,
    ): ExchangeRoomPost {
        if (! $room->isOpen()) {
            throw new \RuntimeException('Cannot post to a closed exchange room.');
        }

        if (! $message && ! $file) {
            throw new \InvalidArgumentException('A post must contain a message, a file, or both.');
        }

        $storagePath = null;
        $fileName = null;
        $mimeType = null;
        $versionNumber = null;

        if ($file) {
            $mimeType = $file->getMimeType();
            if (! in_array($mimeType, self::ALLOWED_MIMES)) {
                throw new \InvalidArgumentException('Only PDF and DOCX files are allowed.');
            }

            $fileName = $file->getClientOriginalName();
            $disk = config('ccrs.contracts_disk', 'local');
            $ext = $file->getClientOriginalExtension();
            $storagePath = sprintf('exchange_rooms/%s/%s.%s', $room->contract_id, Str::uuid(), $ext);
            Storage::disk($disk)->put($storagePath, $file->getContent());

            $versionNumber = ($room->latestVersion() ?? 0) + 1;
        }

        $post = ExchangeRoomPost::create([
            'room_id' => $room->id,
            'author_type' => get_class($author),
            'author_id' => $author->id,
            'author_name' => $author->name,
            'actor_side' => $actorSide,
            'message' => $message,
            'storage_path' => $storagePath,
            'file_name' => $fileName,
            'mime_type' => $mimeType,
            'version_number' => $versionNumber,
        ]);

        AuditService::log('exchange_room.post', 'exchange_room', $room->id, [
            'post_id' => $post->id,
            'actor_side' => $actorSide,
            'file_name' => $fileName,
            'version_number' => $versionNumber,
        ]);

        return $post;
    }

    public function advanceStage(ExchangeRoom $room, string $newStage): void
    {
        if (! $room->isOpen()) {
            throw new \RuntimeException('Cannot advance stage on a closed exchange room.');
        }

        $currentIdx = array_search($room->negotiation_stage, self::STAGE_ORDER);
        $newIdx = array_search($newStage, self::STAGE_ORDER);

        if ($newIdx === false) {
            throw new \InvalidArgumentException("Invalid stage: {$newStage}");
        }

        if ($newIdx <= $currentIdx) {
            throw new \RuntimeException("Cannot move backward from {$room->negotiation_stage} to {$newStage}.");
        }

        $room->update(['negotiation_stage' => $newStage]);

        AuditService::log('exchange_room.stage_changed', 'exchange_room', $room->id, [
            'from' => self::STAGE_ORDER[$currentIdx],
            'to' => $newStage,
        ]);
    }

    public function close(ExchangeRoom $room): void
    {
        $room->update([
            'status' => 'closed',
            'negotiation_stage' => 'final',
        ]);

        AuditService::log('exchange_room.closed', 'exchange_room', $room->id);
    }
}
