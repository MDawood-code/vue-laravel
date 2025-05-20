<?php

namespace App\Http\Traits;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait FileUploadTrait
{
    public function uploadFile(UploadedFile $file, string $folder): string
    {
        $fileName = Str::random(14).'_'.time().'.'.$file->extension();
        $path = 'public/'.$folder;
        $file->storeAs($path, $fileName);

        return Storage::url($path.'/'.$fileName);
    }
}
