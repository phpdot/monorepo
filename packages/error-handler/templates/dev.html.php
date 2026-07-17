<?php
/**
 * PHPdot — Development Error Page
 *
 * @var \PHPdot\ErrorHandler\Context\ErrorContext $errorContext
 */

$e = $errorContext->exception;
$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $h($e::class) ?></title>
<style>
[data-theme="dark"] {
    --bg: #0b0d11; --s1: #13161d; --s2: #1a1e28;
    --bd: #262d3d; --bd2: #1a1e28;
    --t1: #dce1ec; --t2: #7e8aa0; --t3: #4e5770;
    --red: #ef6461; --green: #6bc975; --blue: #5ea3ef;
    --hl: rgba(239,100,97,0.07); --hlg: rgba(239,100,97,0.18);
    --gut: #151820;
}
[data-theme="light"] {
    --bg: #f5f6f8; --s1: #fff; --s2: #f0f1f4;
    --bd: #dcdfe6; --bd2: #ecedf2;
    --t1: #1c2030; --t2: #606a80; --t3: #98a0b4;
    --red: #d94045; --green: #2f8e3c; --blue: #2f7fd4;
    --hl: rgba(217,64,69,0.05); --hlg: rgba(217,64,69,0.12);
    --gut: #f5f6f8;
}
:root {
    --f: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif;
    --m: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
    --r: 8px;
}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:var(--f);background:var(--bg);color:var(--t1);font-size:14px;line-height:1.5}
[hidden]{display:none!important}
.wrap{max-width:960px;margin:0 auto}

.hd{padding:28px 24px 24px;border-bottom:1px solid var(--bd2);position:relative}
.hd-badge{display:inline-block;background:var(--red);color:#fff;font:700 11px/1 var(--m);padding:3px 7px;border-radius:3px;margin-bottom:10px}
.hd-class{font:400 13px/1.3 var(--m);color:var(--t3);margin-bottom:6px}
.hd-msg{font:700 22px/1.3 var(--f);color:var(--t1);margin-bottom:8px;word-break:break-word;padding-right:40px}
.hd-loc{font:400 12px/1 var(--m);color:var(--t2)}
.theme-btn{position:absolute;top:28px;right:24px;width:28px;height:28px;background:var(--s2);border:1px solid var(--bd);border-radius:6px;color:var(--t2);cursor:pointer;font-size:14px;display:flex;align-items:center;justify-content:center;transition:all .15s}
.theme-btn:hover{background:var(--bd);color:var(--t1)}

.sol{margin:16px 24px 0}
.sol-item{background:rgba(107,201,117,0.06);border:1px solid rgba(107,201,117,0.18);border-radius:var(--r);padding:12px 16px;margin-bottom:8px}
.sol-title{font-weight:600;font-size:13px;color:var(--green);margin-bottom:2px}
.sol-desc{font-size:13px;color:var(--t2)}
.sol-link{color:var(--blue);font-size:12px;text-decoration:none;margin-top:4px;display:inline-block}
.sol-link:hover{text-decoration:underline}

.tabs{display:flex;border-bottom:1px solid var(--bd);padding:0 24px;overflow-x:auto;gap:0}
.tabs button{background:none;border:none;border-bottom:2px solid transparent;padding:10px 16px;font:500 13px var(--f);color:var(--t3);cursor:pointer;white-space:nowrap}
.tabs button:hover{color:var(--t2)}
.tabs button.on{color:var(--blue);border-bottom-color:var(--blue)}
.pane{display:none;padding:16px 24px}
.pane.on{display:block}

.fr{border:1px solid var(--bd2);border-radius:var(--r);margin-bottom:6px;overflow:hidden}
.fr.vnd{opacity:0.35}.fr.vnd:hover{opacity:0.8}
.fr.on{border-color:var(--bd)}
.fr-hd{display:flex;align-items:center;gap:10px;padding:8px 12px;cursor:pointer;background:var(--s1)}
.fr-hd:hover{background:var(--s2)}
.fr-n{font:400 11px var(--m);color:var(--t3);min-width:20px;text-align:right}
.fr-f{font:400 12px var(--m);color:var(--t1);flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.fr-f .d{color:var(--t3)}
.fr-fn{font:400 11px var(--m);color:var(--t3)}
.fr-arr{font-size:9px;color:var(--t3);transition:transform .15s}.fr.on .fr-arr{transform:rotate(90deg)}

.code{background:var(--gut);border-top:1px solid var(--bd2);overflow-x:auto}
.code table{border-collapse:collapse;width:100%}
.code td{padding:0;white-space:pre;font:12px/1.7 var(--m);vertical-align:top}
.code .g{width:52px;text-align:right;padding-right:12px;color:var(--t3);user-select:none;background:var(--gut);border-right:1px solid var(--bd2)}
.code .c{padding-left:12px;padding-right:20px}
.code tr.h{background:var(--hl)}
.code tr.h .g{background:var(--hlg);color:var(--red);font-weight:700}

.dt{width:100%;border-collapse:collapse}
.dt th,.dt td{padding:7px 12px;text-align:left;border-bottom:1px solid var(--bd2);font-size:12px}
.dt th{font:500 12px var(--m);color:var(--t3);width:220px;background:var(--s1);vertical-align:top}
.dt td{font:400 12px var(--m);color:var(--t2);word-break:break-all}
.dt tr:last-child th,.dt tr:last-child td{border-bottom:none}
.dt .mask{color:var(--t3);font-style:italic}

.req{display:flex;align-items:center;gap:8px;padding:10px 12px;background:var(--s1);border:1px solid var(--bd2);border-radius:var(--r);margin-bottom:12px}
.req-m{font:700 10px var(--m);color:var(--blue);background:rgba(94,163,239,0.08);border:1px solid rgba(94,163,239,0.18);padding:2px 6px;border-radius:3px}
.req-u{font:400 13px var(--m);color:var(--t1)}

@media(max-width:640px){
    .hd,.pane,.sol{padding-left:16px;padding-right:16px}
    .tabs{padding:0 16px}
    .hd-msg{font-size:18px}
    .dt th{width:120px}
}
</style>
</head>
<body>
<div class="wrap">

<div class="hd">
    <button class="theme-btn" onclick="toggleTheme()" title="Toggle dark/light">◑</button>
    <div class="hd-badge"><?= $errorContext->statusCode ?></div>
    <div class="hd-class"><?= $h($e::class) ?></div>
    <div class="hd-msg"><?= $h($e->getMessage()) ?></div>
    <div class="hd-loc"><?= $h($e->getFile()) ?>:<?= $e->getLine() ?></div>
</div>

<?php if ($errorContext->solutions !== []): ?>
<div class="sol">
    <?php foreach ($errorContext->solutions as $s): ?>
    <div class="sol-item">
        <div class="sol-title"><?= $h($s->title) ?></div>
        <div class="sol-desc"><?= $h($s->description) ?></div>
        <?php foreach ($s->links as $link): ?>
        <a href="<?= $h($link->url) ?>" class="sol-link" target="_blank" rel="noopener"><?= $h($link->label) ?> →</a>
        <?php endforeach ?>
    </div>
    <?php endforeach ?>
</div>
<?php endif ?>

<nav class="tabs" id="tabs">
    <button class="on" data-t="trace">Stack Trace</button>
    <?php if ($errorContext->request !== null): ?>
    <button data-t="request">Request</button>
    <?php endif ?>
    <button data-t="env">Environment</button>
    <?php foreach ($errorContext->context as $i => $tab): ?>
    <button data-t="c<?= $i ?>"><?= $h($tab->label) ?></button>
    <?php endforeach ?>
</nav>

<div class="pane on" id="p-trace">
    <?php foreach ($errorContext->stackTrace->frames as $i => $frame): ?>
    <div class="fr <?= $frame->isApplication ? '' : 'vnd' ?> <?= $i === 0 ? 'on' : '' ?>" id="f<?= $i ?>">
        <div class="fr-hd" onclick="tf(<?= $i ?>)">
            <span class="fr-n"><?= $i ?></span>
            <span class="fr-f"><?= $h($frame->file) ?><span class="d">:<?= $frame->line ?></span></span>
            <?php if ($frame->class !== null || $frame->function !== null): ?>
            <span class="fr-fn"><?= $h(($frame->class ?? '') . ($frame->class !== null ? '::' : '') . ($frame->function ?? '')) ?>()</span>
            <?php endif ?>
            <span class="fr-arr">▶</span>
        </div>
        <?php if ($frame->codeSnippet !== []): ?>
        <div class="code" id="cd<?= $i ?>" <?= $i > 0 ? 'hidden' : '' ?>>
            <table>
            <?php foreach ($frame->codeSnippet as $cl): ?>
            <tr<?= $cl->isHighlighted ? ' class="h"' : '' ?>><td class="g"><?= $cl->lineNumber ?></td><td class="c"><?= $h($cl->code) ?></td></tr>
            <?php endforeach ?>
            </table>
        </div>
        <?php endif ?>
    </div>
    <?php endforeach ?>
</div>

<?php if ($errorContext->request !== null): ?>
<div class="pane" id="p-request">
    <div class="req">
        <span class="req-m"><?= $h($errorContext->request->getMethod()) ?></span>
        <span class="req-u"><?= $h((string) $errorContext->request->getUri()) ?></span>
    </div>
    <table class="dt">
        <?php foreach ($errorContext->request->getHeaders() as $name => $values): ?>
        <tr><th><?= $h((string) $name) ?></th><td><?= $h(implode(', ', $values)) ?></td></tr>
        <?php endforeach ?>
    </table>
</div>
<?php endif ?>

<div class="pane" id="p-env">
    <table class="dt">
        <?php foreach ($errorContext->environment as $key => $value): ?>
        <tr><th><?= $h($key) ?></th><td<?= $value === '********' ? ' class="mask"' : '' ?>><?= $h($value) ?></td></tr>
        <?php endforeach ?>
    </table>
</div>

<?php foreach ($errorContext->context as $i => $tab): ?>
<div class="pane" id="p-c<?= $i ?>">
    <table class="dt">
        <?php foreach ($tab->data as $key => $value): ?>
        <tr><th><?= $h((string) $key) ?></th><td><?php
            if (is_string($value)) { echo $h($value); }
            else { try { echo $h(json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: ''); } catch (\Throwable) { echo $h('[' . get_debug_type($value) . ']'); } }
        ?></td></tr>
        <?php endforeach ?>
    </table>
</div>
<?php endforeach ?>

</div>

<script>
document.getElementById('tabs').onclick=function(e){var b=e.target.closest('button');if(!b)return;document.querySelectorAll('.tabs button').forEach(function(x){x.classList.remove('on')});document.querySelectorAll('.pane').forEach(function(x){x.classList.remove('on')});b.classList.add('on');var p=document.getElementById('p-'+b.dataset.t);if(p)p.classList.add('on')};
function tf(i){var c=document.getElementById('cd'+i),f=document.getElementById('f'+i);if(!c||!f)return;c.hidden=!c.hidden;f.classList.toggle('on',!c.hidden)}
function toggleTheme(){var h=document.documentElement;h.dataset.theme=h.dataset.theme==='dark'?'light':'dark'}
if(window.matchMedia('(prefers-color-scheme:light)').matches)document.documentElement.dataset.theme='light';
</script>
</body>
</html>
