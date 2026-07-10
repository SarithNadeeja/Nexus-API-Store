/**
 * Live terminal typing animation for the homepage hero.
 */
(function () {
  const terminalLines = [
    { text: 'POST /v1/tools/process', className: 'term-cyan' },
    { text: 'Authorization: Bearer ************', className: 'term-muted' },
    { text: '', className: '' },
    { text: '{', className: 'term-purple' },
    { text: '  "source": "example",', className: 'term-light' },
    { text: '  "format": "json"', className: 'term-light' },
    { text: '}', className: 'term-purple' },
  ];

  const responseLines = [
    { text: 'Response:', className: 'term-muted' },
    { text: '{', className: 'term-purple' },
    { text: '  "success": true,', className: 'term-green' },
    { text: '  "request_id": "req_82Kp91",', className: 'term-light' },
    { text: '  "processing_time": "182ms"', className: 'term-light' },
    { text: '}', className: 'term-purple' },
  ];

  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function renderLines(container, lines, showCursor) {
    container.innerHTML = lines
      .map((line, index) => {
        const cursor =
          showCursor && index === lines.length - 1
            ? '<span class="term-cursor">|</span>'
            : '';
        return `<div class="term-line ${line.className}">${escapeHtml(line.text.slice(0, line.visible))}${cursor}</div>`;
      })
      .join('');
  }

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
  }

  function typeLines(lines, speed, onComplete) {
    const output = lines.map((line) => ({ ...line, visible: 0 }));
    let lineIndex = 0;
    let charIndex = 0;

    if (reducedMotion) {
      lines.forEach((line, i) => {
        output[i].visible = line.text.length;
      });
      onComplete(output);
      return;
    }

    const interval = setInterval(() => {
      if (lineIndex >= lines.length) {
        clearInterval(interval);
        onComplete(output);
        return;
      }

      charIndex += 1;
      output[lineIndex].visible = charIndex;

      if (charIndex >= lines[lineIndex].text.length) {
        lineIndex += 1;
        charIndex = 0;
      }

      onComplete([...output]);
    }, speed);
  }

  function initHeroTerminal() {
    const requestEl = document.getElementById('terminal-request');
    const responseEl = document.getElementById('terminal-response');
    if (!requestEl || !responseEl) return;

    typeLines(terminalLines, 25, (lines) => {
      renderLines(requestEl, lines, true);
    });

    const responseDelay = reducedMotion ? 0 : 2800;
    setTimeout(() => {
      typeLines(responseLines, 20, (lines) => {
        renderLines(responseEl, lines, false);
      });
    }, responseDelay);
  }

  function initParticles() {
    const container = document.getElementById('hero-particles');
    if (!container || reducedMotion) return;

    for (let i = 0; i < 40; i += 1) {
      const dot = document.createElement('span');
      dot.className = 'hero-particle';
      dot.style.left = `${(i * 17 + 7) % 100}%`;
      dot.style.top = `${(i * 23 + 11) % 100}%`;
      dot.style.animationDelay = `${i * 0.1}s`;
      dot.style.animationDuration = `${3 + (i % 5)}s`;
      container.appendChild(dot);
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    initParticles();
    initHeroTerminal();
  });
})();
