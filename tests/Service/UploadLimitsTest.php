<?php

namespace App\Tests\Service;

use App\EventListener\RequestTooLargeListener;
use App\Service\UploadLimits;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class UploadLimitsTest extends TestCase
{
    #[DataProvider('iniValues')]
    public function testIniToBytes(string $ini, int $bytes): void
    {
        $this->assertSame($bytes, UploadLimits::iniToBytes($ini));
    }

    public static function iniValues(): array
    {
        return [
            '2M' => ['2M', 2 * 1024 * 1024],
            '8m' => ['8m', 8 * 1024 * 1024],
            '512K' => ['512K', 512 * 1024],
            '1G' => ['1G', 1024 * 1024 * 1024],
            'bajty' => ['1048576', 1048576],
            'bez limitu' => ['-1', 0],
            'prázdne' => ['', 0],
        ];
    }

    public function testFormatMb(): void
    {
        $this->assertSame('8 MB', UploadLimits::formatMb(8 * 1024 * 1024));
        $this->assertSame('1.5 MB', UploadLimits::formatMb(1536 * 1024));
    }

    public function testFindsTooLargeFileAndDescribesIt(): void
    {
        $limits = new UploadLimits(maxFileBytes: 2 * 1024 * 1024);
        $ok = $this->uploaded('ok.jpg', UPLOAD_ERR_OK);
        $big = $this->uploaded('IMG_9999.jpg', UPLOAD_ERR_INI_SIZE);

        $invalid = $limits->findInvalidFile([$ok, $big]);

        $this->assertSame($big, $invalid);
        $this->assertSame(['code' => 'photo_too_large', 'message' => 'Photo "IMG_9999.jpg" is too large (max 2 MB).'], $limits->apiError($invalid));
        $this->assertSame('Fotka „IMG_9999.jpg“ je príliš veľká (najviac 2 MB). Skús menšiu fotku.', $limits->webError($invalid));
    }

    public function testPartialUploadIsReportedAsFailed(): void
    {
        $limits = new UploadLimits(maxFileBytes: 2 * 1024 * 1024);
        $partial = $this->uploaded('a.jpg', UPLOAD_ERR_PARTIAL);

        $this->assertSame('photo_upload_failed', $limits->apiError($limits->findInvalidFile([$partial]))['code']);
    }

    public function testValidFilesAndNullAreIgnored(): void
    {
        $limits = new UploadLimits();

        $this->assertNull($limits->findInvalidFile([$this->uploaded('a.jpg', UPLOAD_ERR_OK), null]));
        $this->assertNull($limits->findInvalidFile([]));
    }

    // --- RequestTooLargeListener (post_max_size) ---

    public function testTooLargeApiRequestReturns413Json(): void
    {
        $response = $this->runListener('/api/mushrooms', 'POST', 9 * 1024 * 1024, maxPost: 8 * 1024 * 1024);

        $this->assertNotNull($response);
        $this->assertSame(413, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('error', $data['status']);
        $this->assertSame('request_too_large', $data['code']);
        $this->assertStringContainsString('8 MB', $data['message']);
        $this->assertNull($data['id']);
    }

    public function testTooLargeWebRequestReturns413WithErrors(): void
    {
        $response = $this->runListener('/rozcestnik/create', 'POST', 9 * 1024 * 1024, maxPost: 8 * 1024 * 1024);

        $this->assertSame(413, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('Fotky sú spolu príliš veľké (najviac 8 MB)', $data['errors'][0]);
    }

    public function testRequestsWithinLimitPass(): void
    {
        $this->assertNull($this->runListener('/api/mushrooms', 'POST', 1024, maxPost: 8 * 1024 * 1024));
        $this->assertNull($this->runListener('/api/mushrooms', 'GET', 9 * 1024 * 1024, maxPost: 8 * 1024 * 1024));
        $this->assertNull($this->runListener('/api/mushrooms', 'POST', 9 * 1024 * 1024, maxPost: 0), 'post_max_size = 0 znamená bez limitu');
    }

    private function runListener(string $path, string $method, int $contentLength, int $maxPost): ?\Symfony\Component\HttpFoundation\Response
    {
        $request = Request::create($path, $method, server: ['CONTENT_LENGTH' => (string) $contentLength]);
        $event = new RequestEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

        (new RequestTooLargeListener(new UploadLimits(maxPostBytes: $maxPost)))($event);

        return $event->getResponse();
    }

    private function uploaded(string $name, int $error): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'upl');

        return new UploadedFile($path, $name, 'image/jpeg', $error, true);
    }
}
