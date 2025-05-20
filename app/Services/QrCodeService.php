<?php

namespace App\Services;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Exception;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;
use Intervention\Image\Image as ImageImage;

class QrCodeService
{
    public function generateQrCodeWithLabel(string $content, string $label, string $folder = 'qr'): string
    {
        $options = new QROptions([
            'outputType' => QRCode::OUTPUT_IMAGE_PNG,
            'eccLevel' => QRCode::ECC_L,
            'imageBase64' => false,
        ]);

        try {
            $qrCode = $this->generateQrCode($content, $options);
            $qrImage = Image::make($qrCode);
            $qrImage->resize(400, null, function ($constraint): void {
                $constraint->aspectRatio();
            });

            $labelImage = $this->createLabelImage($label, 400, 200);
            $mergedImage = $this->mergeImages($qrImage, $labelImage);

            return $this->saveImage($mergedImage, $folder);
        } catch (Exception $e) {
            // Handle error
            return 'Error generating QR code: '.$e->getMessage();
        }
    }

    private function generateQrCode(string $content, QROptions $options): mixed
    {
        return (new QRCode($options))->render($content);
    }

    private function createLabelImage(string $label, int $width, int $height): ImageImage
    {
        $labelImage = Image::canvas($width, $height, '#ffffff');
        $labelImage->text($label, $width / 2, $height / 2, function ($font): void {
            $font->file(public_path('fonts/OpenSans-Regular.ttf'));
            $font->size(24);
            $font->color('#000000');
            $font->align('center');
            $font->valign('middle');
        });

        return $labelImage;
    }

    private function mergeImages(ImageImage $qrImage, ImageImage $labelImage): ImageImage
    {
        $canvas = Image::canvas(400, $qrImage->height() + $labelImage->height());
        $canvas->insert($qrImage, 'top');
        $canvas->insert($labelImage, 'bottom');

        return $canvas;
    }

    private function saveImage(ImageImage $image, string $folder): string
    {
        $fileName = 'qr_'.Str::random(10).'_'.time().'.png';
        $path = $folder.'/'.$fileName;
        $fullFilePath = storage_path('app/public/'.$path);

        if (! file_exists(dirname($fullFilePath))) {
            mkdir(dirname($fullFilePath), 0777, true);
        }
        $image->save($fullFilePath);

        return '/storage/'.$path;
    }
}
