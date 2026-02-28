// ── SEARCH ──────────────────────────────────────────────────────────────────
(function () {
  const index   = window.SEARCH_INDEX || [];
  const srBox   = document.getElementById('search-results');
  const srInput = document.getElementById('search');

  if (!srInput || !srBox) return;

  srInput.addEventListener('input', () => {
    const q = srInput.value.trim().toLowerCase();
    if (!q) { closeSearch(); return; }

    const hits = index.filter(x =>
      x.name.toLowerCase().includes(q) ||
      (x.desc  && x.desc.toLowerCase().includes(q)) ||
      (x.class && x.class.toLowerCase().includes(q))
    ).slice(0, 30);

    if (!hits.length) { closeSearch(); return; }

    srBox.innerHTML = hits.map(h => {
      const url = h.type === 'class'
        ? `?class=${encodeURIComponent(h.name)}`
        : `?class=${encodeURIComponent(h.class)}&method=${encodeURIComponent(h.name)}`;
      const sub = h.type === 'method'
        ? `<span class="sr-class">${esc(h.class)}.</span>` : '';
      return `<div class="sr-item" onclick="location.href='${url}'">
        <span class="sr-badge ${h.type}">${h.type}</span>
        ${sub}<span class="sr-name">${esc(h.name)}</span>
        <span class="sr-desc">${esc(h.desc || '')}</span>
      </div>`;
    }).join('');
    srBox.classList.add('open');
  });

  document.addEventListener('click', e => {
    if (!srInput.contains(e.target) && !srBox.contains(e.target)) closeSearch();
  });

  srInput.addEventListener('keydown', e => {
    if (e.key === 'Escape') { closeSearch(); srInput.blur(); }
  });

  function closeSearch() { srBox.classList.remove('open'); srBox.innerHTML = ''; }

  function esc(s) {
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }
})();

// ── SIDEBAR TREE ─────────────────────────────────────────────────────────────
(function () {
  const treeData    = window.TREE_DATA    || { children: {}, roots: [] };
  const classesData = window.CLASSES_DATA || {};
  const selected    = window.SELECTED_CLASS || '';
  const container   = document.getElementById('sidebar-tree');
  if (!container) return;

  function renderNode(name, depth) {
    const children  = treeData.children[name] || [];
    const isActive  = name === selected;
    const url       = '?class=' + encodeURIComponent(name);
    const cls       = classesData[name];
    const count     = cls ? cls.methodCount : '';

    let html = `<div class="tree-node" style="padding-left:${depth * 14 + 14}px">`;
    html += children.length
      ? `<span class="tree-arrow">▸</span>`
      : `<span class="tree-indent"></span>`;
    html += `<a class="tree-link${isActive ? ' active' : ''}" href="${url}">${name}</a>`;
    if (count !== '') html += `<span class="class-count">${count}</span>`;
    html += `</div>`;

    for (const child of children) {
      html += renderNode(child, depth + 1);
    }
    return html;
  }

  let html = '';
  for (const root of treeData.roots) {
    html += renderNode(root, 0);
  }

  container.innerHTML = html;
})();