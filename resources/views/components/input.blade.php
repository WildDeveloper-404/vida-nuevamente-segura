@props(['name','label','type'=>'text','value'=>null,'required'=>false,'options'=>null,'multiple'=>false,'hint'=>null])
@php($inputId = $attributes->get('id', $name.'-'.\Illuminate\Support\Str::random(6)))
<div class="col-12 col-md-6">
    <label for="{{ $inputId }}" class="form-label">{{ $label }} @if($required)<span class="text-danger" aria-hidden="true">*</span>@endif</label>
    @if($options !== null)
        <select id="{{ $inputId }}" name="{{ $name }}{{ $multiple ? '[]' : '' }}" class="form-select" {{ $required ? 'required' : '' }} {{ $multiple ? 'multiple' : '' }} {{ $attributes->except('id') }}>
            @unless($multiple)<option value="">Seleccione…</option>@endunless
            @foreach($options as $key=>$text)<option value="{{ $key }}" @selected($multiple ? in_array((string)$key, array_map('strval',(array)old($name,$value??[]))) : (string)old($name,$value)===(string)$key)>{{ $text }}</option>@endforeach
        </select>
    @elseif($type === 'textarea')
        <textarea id="{{ $inputId }}" name="{{ $name }}" class="form-control" rows="3" {{ $required ? 'required' : '' }} {{ $attributes->except('id') }}>{{ old($name,$value) }}</textarea>
    @else
        <input id="{{ $inputId }}" name="{{ $name }}" type="{{ $type }}" class="form-control" value="{{ $type === 'password' ? '' : old($name,$value) }}" {{ $required ? 'required' : '' }} {{ $attributes->except('id') }}>
    @endif
    @if($hint)<div class="form-text">{{ $hint }}</div>@endif
    @error($name)<div class="text-danger small">{{ $message }}</div>@enderror
</div>
