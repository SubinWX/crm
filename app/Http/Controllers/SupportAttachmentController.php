<?php

namespace App\Http\Controllers;

use App\Models\SupportAttachment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class SupportAttachmentController extends Controller
{
    /** Customer download: only files on their own tickets, never files on internal notes. */
    public function portal(SupportAttachment $attachment)
    {
        $message = $attachment->message()->with('ticket')->firstOrFail();
        abort_unless($message->ticket->portal_user_id === Auth::guard('portal')->id() && ! $message->is_internal, 404);

        return $this->send($attachment);
    }

    /** Team download (route is limited to support.manage). */
    public function staff(SupportAttachment $attachment)
    {
        return $this->send($attachment);
    }

    protected function send(SupportAttachment $attachment)
    {
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        return Storage::disk('local')->download($attachment->path, $attachment->name);
    }
}
