@if($status == 'pending')
    {{ $label . ' ' . $label}}-warning
@elseif($status == 'confirmed')
    {{ $label . ' ' . $label}}-info
@elseif($status == 'Close')
    {{ $label . ' ' . $label}}-danger
@elseif($status == 'open')
    {{ $label . ' ' . $label}}-success
@endif
