<style>
.sm-form{max-width:420px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;color:#1f2937}
.sm-form h3{margin:0 0 14px;font-size:20px}
.sm-form label{display:block;margin:0 0 4px;font-size:14px;font-weight:500}
.sm-form .sm-row{margin-bottom:12px}
.sm-form input[type=text],.sm-form input[type=email],.sm-form input[type=number],.sm-form input[type=date],.sm-form select{width:100%;box-sizing:border-box;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:14px}
.sm-form .sm-check{display:flex;gap:8px;align-items:flex-start;font-size:13px;font-weight:400}
.sm-form button{width:100%;padding:10px 14px;border:0;border-radius:8px;background:{{ \App\Services\Branding::clientColor($form->list->workspace) }};color:#fff;font-size:15px;font-weight:600;cursor:pointer}
.sm-form .sm-hp{position:absolute;left:-5000px}
</style>
<form class="sm-form" method="POST" action="{{ route('subscribe.store', $form->uid) }}">
@if ($form->title)
    <h3>{{ $form->title }}</h3>
@endif
    <div class="sm-row"><label for="sm-email-{{ $form->uid }}">Email *</label><input id="sm-email-{{ $form->uid }}" type="email" name="EMAIL" required></div>
@foreach ($form->visibleFields() as $field)
    <div class="sm-row">
    @if ($field->type === 'checkbox')
        <label class="sm-check"><input type="checkbox" name="{{ $field->tag }}" value="1"> {{ $field->label }}</label>
    @elseif ($field->type === 'dropdown')
        <label>{{ $field->label }}{{ $field->required ? ' *' : '' }}</label><select name="{{ $field->tag }}" @required($field->required)><option value=""></option>@foreach ($field->options ?? [] as $option)<option>{{ $option }}</option>@endforeach</select>
    @else
        <label>{{ $field->label }}{{ $field->required ? ' *' : '' }}</label><input type="{{ ['number' => 'number', 'date' => 'date', 'phone' => 'tel'][$field->type] ?? 'text' }}" name="{{ $field->tag }}" @required($field->required)>
    @endif
    </div>
@endforeach
@if ($form->consent_enabled)
    <div class="sm-row"><label class="sm-check"><input type="checkbox" name="consent" value="1" required> <span>{!! strip_tags($form->consent_text, '<a><strong><em>') !!}</span></label></div>
@endif
    <div class="sm-hp" aria-hidden="true"><input type="text" name="website_url" tabindex="-1" autocomplete="off"></div>
    <button type="submit">{{ $form->button_text }}</button>
</form>
