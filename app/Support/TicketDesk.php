<?php

namespace App\Support;

use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/** Shared bits of the ticket workflow used by both the portal and the team desk. */
class TicketDesk
{
    public const ATTACHMENT_RULES = ['file', 'max:10240', 'mimes:jpg,jpeg,png,webp,gif,pdf,doc,docx,xls,xlsx,csv,txt,zip'];

    public const MAX_ATTACHMENTS = 5;

    /** Team members who hear about ticket activity: Super Admins plus Staff allowed to answer tickets. */
    public static function team(): Collection
    {
        return User::query()->active()->whereNotNull('password')
            ->where(fn ($q) => $q->where('role', User::ROLE_SUPER_ADMIN)
                ->orWhere(fn ($q) => $q->where('role', User::ROLE_STAFF)->whereJsonContains('permissions', 'support.manage')))
            ->get();
    }

    /** Who should hear about a customer reply: the assignee if there is one, otherwise the whole team. */
    public static function recipientsFor(SupportTicket $ticket): Collection
    {
        $assignee = $ticket->assignee;

        return $assignee && $assignee->is_active ? collect([$assignee]) : self::team();
    }

    /** @param  array<int, UploadedFile|TemporaryUploadedFile>  $files */
    public static function storeAttachments(SupportMessage $message, array $files): void
    {
        foreach ($files as $file) {
            $message->attachments()->create([
                'name' => $file->getClientOriginalName(),
                'path' => $file->store("support/{$message->support_ticket_id}", 'local'),
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);
        }
    }
}
