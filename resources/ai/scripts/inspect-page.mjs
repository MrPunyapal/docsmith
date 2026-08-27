#!/usr/bin/env node
import { createRequire } from 'node:module';
import { readFileSync } from 'node:fs';
import path from 'node:path';

const args = parseArgs(process.argv.slice(2));
const cwd = path.resolve(args.cwd || process.cwd());
const url = args.url || '';
const limit = Math.min(Math.max(parseInt(args.limit || '40', 10) || 40, 1), 80);

if (!/^https?:\/\//i.test(url)) {
  fail('inspect requires a http(s) --url of a running app page.');
}

const chromium = loadChromium(cwd);
const instructions = loadInstructions(args['steps-file']);

const browser = await chromium.launch({ headless: true });

try {
  const page = await browser.newPage({ viewport: { width: 1280, height: 800 } });
  await page.goto(url, { waitUntil: 'networkidle', timeout: 30000 });
  await runSteps(page, instructions.before);

  const waitFor = args['wait-for'] || '';
  if (waitFor) {
    await page.waitForSelector(waitFor, { state: 'visible', timeout: 15000 });
  }

  const delay = parseInt(args.delay || '0', 10);
  if (delay > 0) {
    await page.waitForTimeout(delay);
  }

  await runSteps(page, instructions.steps);

  const payload = await page.evaluate((max) => {
    const interesting = [
      'button',
      'a[href]',
      'input',
      'select',
      'textarea',
      '[role="button"]',
      '[role="dialog"]',
      '[role="listbox"]',
      '[role="option"]',
      '[role="combobox"]',
      '[role="menu"]',
      '[role="tab"]',
      'h1',
      'h2',
      '[data-filament]',
      '.fi-modal',
      '.fi-dropdown',
      '.fi-select-panel',
      '.fi-select-input',
      '.fi-field',
      '.fi-ta',
      '.fi-wi',
    ].join(',');

    const seen = new Set();
    const elements = [];

    const cssEscape = (value) => {
      if (typeof CSS !== 'undefined' && typeof CSS.escape === 'function') {
        return CSS.escape(value);
      }
      return String(value).replace(/[^a-zA-Z0-9_-]/g, '\\$&');
    };

    const suggestSelector = (el) => {
      // Filament generates random ids for teleported dropdown panels. Those
      // ids work only for the current page instance, so prefer the stable
      // component classes for them.
      if (el.id && !/^fi-select-input-(dropdown|option)-/.test(el.id)) {
        return '#' + cssEscape(el.id);
      }

      const dataTestId = el.getAttribute('data-testid');
      if (dataTestId) {
        return `[data-testid="${dataTestId.replace(/"/g, '\\"')}"]`;
      }

      const wireKey = el.getAttribute('wire:key');
      if (wireKey) {
        // Livewire prefixes wire:key values with a request-specific id. Keep
        // the stable form path so the selector survives a fresh capture.
        const stableKey = wireKey.replace(/^[^.]+(?=\.form\.)/, '');
        return `[wire\\:key*="${stableKey.replace(/"/g, '\\"')}"]`;
      }

      const classList = [...el.classList].filter((name) =>
        /^(fi-|filament-)/.test(name) && !/hover|focus|active|open$/.test(name),
      );
      if (classList.length > 0) {
        return '.' + classList.slice(0, 2).join('.');
      }

      const name = el.getAttribute('name');
      if (name) {
        return `${el.tagName.toLowerCase()}[name="${name.replace(/"/g, '\\"')}"]`;
      }

      const role = el.getAttribute('role');
      if (role) {
        return `[role="${role}"]`;
      }

      return el.tagName.toLowerCase();
    };

    for (const el of document.querySelectorAll(interesting)) {
      const rect = el.getBoundingClientRect();
      if (rect.width < 4 || rect.height < 4) {
        continue;
      }
      if (rect.bottom < 0 || rect.right < 0 || rect.top > window.innerHeight || rect.left > window.innerWidth) {
        continue;
      }

      const style = window.getComputedStyle(el);
      if (style.visibility === 'hidden' || style.display === 'none' || Number(style.opacity) === 0) {
        continue;
      }

      const selector = suggestSelector(el);
      const key = selector + '@' + Math.round(rect.x) + ',' + Math.round(rect.y);
      if (seen.has(key)) {
        continue;
      }
      seen.add(key);

      const text = (el.innerText || el.getAttribute('aria-label') || el.getAttribute('placeholder') || '')
        .replace(/\s+/g, ' ')
        .trim()
        .slice(0, 80);

      elements.push({
        selector,
        tag: el.tagName.toLowerCase(),
        role: el.getAttribute('role') || '',
        text,
        classes: [...el.classList].slice(0, 6).join(' '),
        box: {
          x: Math.round(rect.x),
          y: Math.round(rect.y),
          width: Math.round(rect.width),
          height: Math.round(rect.height),
        },
      });

      if (elements.length >= max) {
        break;
      }
    }

    return {
      success: true,
      title: document.title || '',
      elements,
    };
  }, limit);

  payload.url = url;
  process.stdout.write(JSON.stringify(payload) + '\n');
} catch (error) {
  process.stdout.write(JSON.stringify({
    success: false,
    error: error instanceof Error ? error.message : String(error),
  }) + '\n');
  process.exitCode = 1;
} finally {
  await browser.close();
}

function loadChromium(projectRoot) {
  const candidates = [
    path.join(projectRoot, 'package.json'),
    path.join(projectRoot, 'node_modules', 'playwright', 'package.json'),
    path.join(projectRoot, 'node_modules', 'playwright-core', 'package.json'),
  ];

  for (const file of candidates) {
    try {
      const require = createRequire(file);
      try {
        return require('playwright').chromium;
      } catch {
        return require('playwright-core').chromium;
      }
    } catch {
      // try next
    }
  }

  fail('Playwright is not installed. Run: npm install -D playwright capturist && npx playwright install chromium');
}

function loadInstructions(stepsFile) {
  if (!stepsFile) {
    return { before: [], steps: [] };
  }

  const raw = JSON.parse(readFileSync(stepsFile, 'utf8'));
  if (Array.isArray(raw)) {
    return { before: [], steps: raw };
  }
  if (raw && typeof raw === 'object') {
    return {
      before: Array.isArray(raw.before) ? raw.before : [],
      steps: Array.isArray(raw.steps) ? raw.steps : [],
    };
  }

  return { before: [], steps: [] };
}

async function runSteps(page, steps) {
  for (const step of steps) {
    if (!step || typeof step.action !== 'string') {
      continue;
    }

    switch (step.action) {
      case 'goto': {
        const target = step.url || '';
        let resolved = target;

        if (/^(https?:|data:|blob:)/.test(target)) {
          resolved = target;
        } else if (target.startsWith('/')) {
          const cur = new URL(page.url());
          resolved = `${cur.protocol}//${cur.host}${target}`;
        } else if (target !== '') {
          resolved = new URL(target, page.url()).href;
        }

        await page.goto(resolved, { waitUntil: 'load', timeout: 30000 });
        break;
      }
      case 'click':
        await page.click(step.selector);
        break;
      case 'dblclick':
        await page.dblclick(step.selector);
        break;
      case 'hover':
        await page.hover(step.selector);
        break;
      case 'fill':
        await page.fill(step.selector, step.value ?? '');
        break;
      case 'type':
        await page.type(step.selector, step.text ?? '', { delay: step.delay ?? 25 });
        break;
      case 'press':
        if (step.selector) {
          await page.press(step.selector, step.key);
        } else {
          await page.keyboard.press(step.key);
        }
        break;
      case 'scroll':
        if (step.selector) {
          await page.locator(step.selector).first().scrollIntoViewIfNeeded();
        } else {
          await page.mouse.wheel(step.x ?? 0, step.y ?? 0);
        }
        break;
      case 'wait':
        if (step.selector) {
          await page.waitForSelector(step.selector, { state: 'visible' });
        }
        if (typeof step.ms === 'number' && step.ms > 0) {
          await page.waitForTimeout(step.ms);
        }
        break;
      default:
        break;
    }
  }
}

function parseArgs(argv) {
  const out = {};

  for (let i = 0; i < argv.length; i++) {
    const token = argv[i];
    if (!token.startsWith('--')) {
      continue;
    }

    const eq = token.indexOf('=');
    if (eq !== -1) {
      out[token.slice(2, eq)] = token.slice(eq + 1);
      continue;
    }

    const key = token.slice(2);
    const next = argv[i + 1];
    if (next && !next.startsWith('--')) {
      out[key] = next;
      i++;
    } else {
      out[key] = '1';
    }
  }

  return out;
}

function fail(message) {
  process.stdout.write(JSON.stringify({ success: false, error: message }) + '\n');
  process.exit(1);
}
