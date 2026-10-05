<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\PaymentReceipt;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentReceiptEvidenceStorage
{
    /** @return array{file_disk:string,file_path:string,file_mime:string,file_size:int,file_sha256:string} */
    public function store(UploadedFile $file): array
    {
        $mime = (string) $file->getMimeType();
        $clientExtension = strtolower($file->getClientOriginalExtension());
        $extensionsByMime = [
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
            'image/webp' => ['webp'],
            'application/pdf' => ['pdf'],
        ];
        if (! isset($extensionsByMime[$mime]) || ! in_array($clientExtension, $extensionsByMime[$mime], true)) {
            abort(422, 'قالب واقعی فایل با پسوند مجاز رسید سازگار نیست.');
        }

        $extension = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
        };
        $disk = Storage::disk('payment_receipts');
        $path = Str::uuid().'.'.$extension;
        $disk->putFileAs('', $file, $path, ['visibility' => 'private']);
        $absolutePath = $disk->path($path);
        $sha256 = hash_file('sha256', $absolutePath);
        if (! is_string($sha256)) {
            $disk->delete($path);
            abort(500, 'خواندن امن فایل بارگذاری‌شده ممکن نشد.');
        }

        return [
            'file_disk' => 'payment_receipts',
            'file_path' => $path,
            'file_mime' => $mime,
            'file_size' => (int) filesize($absolutePath),
            'file_sha256' => $sha256,
        ];
    }

    public function delete(string $path): void
    {
        if (preg_match('/\A[a-f0-9-]{36}\.(jpg|png|webp|pdf)\z/', $path) === 1) {
            Storage::disk('payment_receipts')->delete($path);
        }
    }

    public function download(PaymentReceipt $receipt): StreamedResponse
    {
        $extensionsByMime = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];
        abort_unless(
            $receipt->file_disk === 'payment_receipts'
                && isset($extensionsByMime[$receipt->file_mime])
                && preg_match('/\A[a-f0-9-]{36}\.(jpg|png|webp|pdf)\z/', $receipt->file_path) === 1,
            404,
        );

        $disk = Storage::disk('payment_receipts');
        $path = $disk->path($receipt->file_path);
        abort_unless(is_file($path) && (int) filesize($path) === (int) $receipt->file_size, 404);
        $hash = hash_file('sha256', $path);
        abort_unless(is_string($hash) && hash_equals($receipt->file_sha256, $hash), 404);

        AuditEvent::create([
            'actor_id' => auth()->id(),
            'request_id' => request()->attributes->get('request_id'),
            'event_name' => 'payment_receipt.file_downloaded',
            'subject_type' => PaymentReceipt::class,
            'subject_id' => $receipt->id,
            'metadata' => ['file_sha256' => $receipt->file_sha256, 'file_mime' => $receipt->file_mime],
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);

        return response()->streamDownload(
            static function () use ($path): void {
                $stream = fopen($path, 'rb');
                if ($stream !== false) {
                    fpassthru($stream);
                    fclose($stream);
                }
            },
            'payment-receipt-'.$receipt->id.'.'.$extensionsByMime[$receipt->file_mime],
            [
                'Content-Type' => $receipt->file_mime,
                'Cache-Control' => 'private, no-store, max-age=0',
                'Pragma' => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
                'Referrer-Policy' => 'no-referrer',
            ],
        );
    }
}
