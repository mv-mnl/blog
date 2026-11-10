{{-- Editor visual (Tiptap). La barra de formato es única y está arriba del todo. $name, $value, $placeholder, $small --}}
<div data-rich-editor data-placeholder="{{ $placeholder }}" class="{{ $small ?? false ? 'is-small' : '' }}">
    <div data-content></div>
    <textarea name="{{ $name }}" hidden data-value>{{ $value }}</textarea>
</div>
