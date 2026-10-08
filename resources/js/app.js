import './bootstrap';
import './charts/equityCurve';
import './charts/signalChart';
import './charts/tradingviewChart';
import './terminalFeed';
import hljs from 'highlight.js/lib/core';
import bash from 'highlight.js/lib/languages/bash';
import javascript from 'highlight.js/lib/languages/javascript';
import php from 'highlight.js/lib/languages/php';
import python from 'highlight.js/lib/languages/python';
import json from 'highlight.js/lib/languages/json';
import http from 'highlight.js/lib/languages/http';

// Livewire bundles Alpine (with navigate) and bootstraps it.
// Avoid overwriting it to keep wire:navigate working.

hljs.registerLanguage('bash', bash);
hljs.registerLanguage('javascript', javascript);
hljs.registerLanguage('php', php);
hljs.registerLanguage('python', python);
hljs.registerLanguage('json', json);
hljs.registerLanguage('http', http);
window.hljs = hljs;
