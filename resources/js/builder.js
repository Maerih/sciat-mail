import grapesjs from 'grapesjs';
import 'grapesjs/dist/css/grapes.min.css';
import newsletter from 'grapesjs-preset-newsletter';

const cfg = window.builderConfig;
const status = document.getElementById('builder-status');
let dirty = false;

const editor = grapesjs.init({
    container: '#gjs',
    height: '100%',
    fromElement: false,
    storageManager: false,
    plugins: [newsletter],
    pluginsOpts: { [newsletter]: { modalTitleImport: 'Import HTML', modalLabelImport: 'Paste your email HTML' } },
    assetManager: {
        assets: cfg.assets,
        upload: cfg.uploadUrl,
        uploadName: 'files',
        headers: { 'X-CSRF-TOKEN': cfg.csrf, Accept: 'application/json' },
        autoAdd: true,
    },
    deviceManager: {
        devices: [
            { id: 'desktop', name: 'Desktop', width: '' },
            { id: 'mobile', name: 'Mobile', width: '375px', widthMedia: '480px' },
        ],
    },
});

// Blocks for things every marketing email needs
const bm = editor.BlockManager;
bm.add('sm-greeting', {
    label: 'Greeting', category: 'Personalise',
    content: '<p style="font-family:Arial,sans-serif;font-size:16px;color:#1f2937;margin:0 0 16px">Hi {{ SUBSCRIBER_FIRST_NAME | there }},</p>',
});
bm.add('sm-view-online', {
    label: 'View online link', category: 'Personalise',
    content: '<p style="font-family:Arial,sans-serif;font-size:12px;text-align:right;margin:0;padding:8px 0"><a href="{{ WEB_VIEW_URL }}" style="color:#6b7280">View in browser</a></p>',
});
bm.add('sm-footer', {
    label: 'Unsubscribe footer', category: 'Personalise',
    content: '<table width="100%" cellpadding="0" cellspacing="0" style="margin-top:24px"><tr><td style="padding:20px;text-align:center;font-family:Arial,sans-serif;font-size:12px;line-height:1.6;color:#6b7280;background:#f9fafb">'
        + 'You received this email because you subscribed to {{ LIST_NAME }}.<br>{{ CONTACT_ADDRESS }}<br><a href="{{ UNSUBSCRIBE_URL }}" style="color:#6b7280">Unsubscribe</a></td></tr></table>',
});
bm.add('sm-signature', {
    label: 'Default signature', category: 'Signatures',
    content: '<div style="font-family:Arial,sans-serif;font-size:14px;margin:16px 0">{{ SIGNATURE }}</div>',
});
// A copy of each saved signature, editable inside this email
(cfg.signatures || []).forEach((signature, i) => bm.add('sm-signature-' + i, { label: signature.name, category: 'Signatures', content: signature.html }));

// Load the saved design, or import existing HTML (head <style> blocks become editable CSS)
if (cfg.project) {
    editor.loadProjectData(JSON.parse(cfg.project));
} else if (cfg.html) {
    const doc = new DOMParser().parseFromString(cfg.html, 'text/html');
    const css = [...doc.querySelectorAll('style')].map((s) => s.textContent).join('\n');
    editor.setComponents(doc.body ? doc.body.innerHTML : cfg.html);
    if (css) {
        editor.setStyle(css);
    }
    // body-level styles (fonts, background) live on the wrapper so they survive editing
    const bodyStyle = doc.body?.getAttribute('style');
    if (bodyStyle) {
        editor.getWrapper().setStyle(Object.fromEntries(bodyStyle.split(';').map((d) => d.split(':').map((x) => x.trim())).filter(([k, v]) => k && v)));
    }
} else {
    editor.setComponents('<table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6"><tr><td align="center" style="padding:24px 12px">'
        + '<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff"><tr><td style="padding:32px;font-family:Arial,sans-serif;font-size:15px;line-height:1.6;color:#1f2937">'
        + '<h1 style="margin:0 0 16px;font-size:24px">Your headline</h1><p>Hi {{ SUBSCRIBER_FIRST_NAME | there }},</p><p>Drag blocks from the panel on the right to build your email.</p>'
        + '</td></tr></table></td></tr></table>');
}

editor.on('update', () => {
    dirty = true;
    status.textContent = 'Unsaved changes';
});

function exportHtml() {
    // The preset returns the wrapper as its own <body>; unwrap it so styles land on the real body
    const doc = new DOMParser().parseFromString(editor.runCommand('gjs-get-inlined-html'), 'text/html');
    const style = ('margin:0;padding:0;' + (doc.body.getAttribute('style') || '')).replace(/"/g, "'");
    return '<!DOCTYPE html>\n<html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title></title></head>\n'
        + '<body style="' + style + '">' + doc.body.innerHTML + '</body></html>';
}

async function save() {
    status.textContent = 'Saving…';
    const res = await fetch(cfg.saveUrl, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': cfg.csrf },
        body: JSON.stringify({ html: exportHtml(), project: JSON.stringify(editor.getProjectData()) }),
    });
    if (res.ok) {
        dirty = false;
        status.textContent = 'Saved ' + new Date().toLocaleTimeString();
    } else {
        status.textContent = 'Save failed (' + res.status + ')';
    }
    return res.ok;
}

document.getElementById('builder-save').addEventListener('click', save);
document.getElementById('builder-done').addEventListener('click', async () => {
    if (await save()) {
        window.location = cfg.backUrl;
    }
});
document.getElementById('builder-tag').addEventListener('change', (e) => {
    if (!e.target.value) {
        return;
    }
    navigator.clipboard.writeText('{{ ' + e.target.value + ' }}');
    status.textContent = 'Copied {{ ' + e.target.value + ' }} — paste it into a text block';
    e.target.value = '';
});
document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        save();
    }
});
window.addEventListener('beforeunload', (e) => {
    if (dirty) {
        e.preventDefault();
    }
});
