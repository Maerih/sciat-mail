<!DOCTYPE html>
<html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"></head>
<body style="margin:0;padding:0;background:#f4f5f7;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;color:#1f2937">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f5f7;padding:32px 12px"><tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:12px;padding:32px">
<tr><td style="font-size:15px;line-height:1.6">
@yield('content')
</td></tr></table>
@hasSection('footer')<p style="font-size:12px;color:#9ca3af;margin-top:16px">@yield('footer')</p>@endif
</td></tr></table>
</body></html>
