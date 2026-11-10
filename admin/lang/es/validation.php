<?php

return [
    'required' => 'El campo :attribute es obligatorio.',
    'string' => 'El campo :attribute debe ser texto.',
    'max' => [
        'string' => 'El campo :attribute no puede tener más de :max caracteres.',
        'file' => 'El archivo :attribute no puede pesar más de :max KB.',
    ],
    'alpha_dash' => 'La :attribute solo puede tener letras, números, guiones y guiones bajos.',
    'in' => 'El valor de :attribute no es válido.',
    'date' => 'La :attribute no es una fecha válida.',
    'boolean' => 'El campo :attribute debe ser verdadero o falso.',
    'image' => 'El archivo :attribute debe ser una imagen.',
    'mimes' => 'El archivo :attribute debe ser de tipo: :values.',
    'uploaded' => 'No se pudo subir :attribute (¿es demasiado grande?).',

    'attributes' => [
        'title' => 'título',
        'slug' => 'URL',
        'excerpt' => 'resumen',
        'body' => 'contenido',
        'tags' => 'etiquetas',
        'status' => 'estado',
        'published_at' => 'fecha de publicación',
        'cover' => 'portada',
        'image' => 'imagen',
        'update_body' => 'actualización',
    ],
];
