@props(['value'])

<label {{ $attributes->merge(['class' => 'input-label']) }}>
    {{ $value ?? $slot }}
</label>
