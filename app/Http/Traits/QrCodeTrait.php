<?php

namespace App\Http\Traits;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Exception;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;

trait QrCodeTrait
{
    public function generateQrCodeWithLabel(string $content, string $label, string $folder = 'qr')
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
            echo 'Error generating QR code: ',  $e->getMessage(), "\n";
        }

        return null;
    }

    public function generateQrCode($content, $options)
    {
        return (new QRCode($options))->render($content);
    }

    public function createLabelImage($label, $width, $height)
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

    public function mergeImages($qrImage, $labelImage)
    {
        $canvas = Image::canvas(400, $qrImage->height() + $labelImage->height());
        $canvas->insert($qrImage, 'top');
        $canvas->insert($labelImage, 'bottom');

        return $canvas;
    }

    public function saveImage($image, string $folder)
    {
        $fileName = 'qr_'.Str::random(10).'_'.time().'.png';
        $path = storage_path('app/public/'.$folder.'/'.$fileName);
        $f = storage_path('app/public/'.$folder);
        if (! file_exists($f)) {
            mkdir($f, 0777, true);
        }
        $image->save($path);

        return $path;
    }
}
