<?php

namespace App\Models;

use App\Support\Html;
use Illuminate\Database\Eloquent\Model;

/** Solo lectura: el esquema y los datos los gestiona la app admin. */
class PostUpdate extends Model
{
    public function html(string $idPrefix = ''): string
    {
        return Html::render($this->body, $idPrefix)[0];
    }

    public function coverUrl(): ?string
    {
        return $this->cover_image ? '/uploads/'.$this->cover_image : null;
    }
}
