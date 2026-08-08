import { readdirSync, readFileSync, statSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { join, relative } from 'node:path';

const repositoryRoot = fileURLToPath(new URL('../', import.meta.url));
const auditRoots = ['app', 'addons', 'public', 'database', 'prototype', 'deploy'];
const sourceExtensions = new Set(['.js', '.mjs', '.php', '.json', '.sql', '.yml', '.yaml']);

function walk(directory) {
  if (!statSafe(directory)?.isDirectory()) return [];
  return readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
    const path = join(directory, entry.name);
    if (entry.isDirectory()) return walk(path);
    return [path];
  });
}

function statSafe(path) {
  try {
    return statSync(path);
  } catch {
    return null;
  }
}

const files = auditRoots
  .flatMap((root) => walk(join(repositoryRoot, root)))
  .filter((path) => sourceExtensions.has(path.slice(path.lastIndexOf('.'))));

const findings = [];
const addFinding = (rule, path, detail) => findings.push({ rule, path: relative(repositoryRoot, path), detail });

for (const path of files) {
  const text = readFileSync(path, 'utf8');
  const relativePath = relative(repositoryRoot, path);

  if (/-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----/.test(text)) {
    addFinding('secret-material', path, 'private key marker found');
  }
  if (/\bsk_(?:live|test)_[A-Za-z0-9]{16,}\b/.test(text)) {
    addFinding('secret-material', path, 'Stripe-like secret key found');
  }
  if (/\bAKIA[0-9A-Z]{16}\b/.test(text)) {
    addFinding('secret-material', path, 'AWS access-key marker found');
  }
  if (/(?:['"])?cash_mode(?:['"])?\s*(?:=>|:|=)\s*true\b/i.test(text)) {
    addFinding('cash-disabled', path, 'cash_mode is explicitly enabled');
  }

  if (/^database[\\/]migrations[\\/]/.test(relativePath) && /\b(?:FLOAT|DOUBLE)\b/i.test(text)) {
    addFinding('integer-money', path, 'FLOAT/DOUBLE is used in a migration');
  }

  if (path.endsWith('.php') && /(?<!->)(?<!::)\b(?:eval|assert|shell_exec|exec|system|passthru|proc_open|popen|unserialize)\s*\(/.test(text)) {
    addFinding('unsafe-runtime', path, 'dynamic execution or unsafe deserialization call found');
  }
}

const report = {
  status: findings.length === 0 ? 'passed' : 'failed',
  files_scanned: files.length,
  rules: ['secret-material', 'cash-disabled', 'integer-money', 'unsafe-runtime'],
  findings,
};

process.stdout.write(`${JSON.stringify(report, null, 2)}\n`);
if (findings.length > 0) process.exitCode = 1;
