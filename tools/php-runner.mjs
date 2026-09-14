/**
 * Dev-only helper: executes PHP CLI scripts through the php-wasm runtime.
 * Used because this sandbox has no native PHP binary. Production uses real PHP 8.2+.
 *
 * Usage: node tools/php-runner.mjs [-l] <script.php> [args...]
 */
import { loadNodeRuntime, createNodeFsMountHandler } from '@php-wasm/node';
import { PHP } from '@php-wasm/universal';
import path from 'node:path';

const argv = process.argv.slice(2);
const lintMode = argv[0] === '-l';
const args = lintMode ? argv.slice(1) : argv;
const script = args[0];
if (!script) {
	console.error('usage: php-runner.mjs [-l] <script.php> [args...]');
	process.exit(2);
}

const ROOT = process.env.MR_ROOT || path.resolve(path.dirname(new URL(import.meta.url).pathname), '..');
const php = new PHP(await loadNodeRuntime('8.2'));
php.mkdir('/app');
await php.mount('/app', createNodeFsMountHandler(ROOT));
php.chdir('/app');

const abs = path.resolve(script);
const rel = abs.startsWith(ROOT) ? '/app/' + path.relative(ROOT, abs) : script;

async function safeRun(opts) {
	try {
		return await php.run(opts);
	} catch (e) {
		const r = e.response;
		if (r) return { text: r.text, errors: r.errors, exitCode: r.exitCode };
		throw e;
	}
}

if (lintMode) {
	const res = await safeRun({
		code: `<?php
$src = file_get_contents(${JSON.stringify(rel)});
try { @token_get_all($src, TOKEN_PARSE); echo "OK ${rel}\\n"; }
catch (\\ParseError $e) { echo "PARSE ERROR ${rel}: ", $e->getMessage(), " on line ", $e->getLine(), "\\n"; exit(1); }
`,
	});
	const out = (res.text || '') + (res.errors || '');
	if (/PARSE ERROR|Fatal error/.test(out) || res.exitCode) {
		console.error(out.trim());
		process.exit(1);
	}
	console.log(out.trim());
	process.exit(0);
}

const res = await safeRun({
	scriptPath: rel,
	argv: [rel, ...args.slice(1)],
	env: { ...process.env, MR_CLI: '1' },
});
process.stdout.write(res.text || '');
if (res.errors) process.stderr.write(res.errors);
process.exit(res.exitCode ?? 0);
