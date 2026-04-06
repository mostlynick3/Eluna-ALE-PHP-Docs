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
    );

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

// ── THEME ────────────────────────────────────────────────────────────────────
(function () {
  const btn  = document.getElementById('theme-toggle');
  const sun  = document.getElementById('theme-icon-sun');
  const moon = document.getElementById('theme-icon-moon');
  if (!btn) return;

  function setTheme(light) {
    document.body.classList.toggle('light', light);
    sun.style.display  = light ? 'none'  : '';
    moon.style.display = light ? ''      : 'none';
    localStorage.setItem('theme', light ? 'light' : 'dark');
  }

  setTheme(localStorage.getItem('theme') === 'light');

  btn.addEventListener('click', () => {
    setTheme(!document.body.classList.contains('light'));
  });
})();

// ── SIDEBAR TREE ─────────────────────────────────────────────────────────────
(function () {
  const treeData    = window.TREE_DATA    || { children: {}, roots: [] };
  const classesData = window.CLASSES_DATA || {};
  const selected    = window.SELECTED_CLASS || '';
  const container   = document.getElementById('sidebar-tree');
  if (!container) return;

  const collapsed = new Set();

  function renderNode(name, depth) {
    const children = treeData.children[name] || [];
    const isActive = name === selected;
    const url      = '?class=' + encodeURIComponent(name);
    const cls      = classesData[name];
    const count    = cls ? cls.methodCount : '';
    const isCollapsed = collapsed.has(name);

    let html = `<div class="tree-node" style="padding-left:${depth * 14 + 14}px">`;
    if (children.length) {
      html += `<span class="tree-arrow" data-node="${name}" style="cursor:pointer">${isCollapsed ? '▸' : '▾'}</span>`;
    } else {
      html += `<span class="tree-indent"></span>`;
    }
    html += `<a class="tree-link${isActive ? ' active' : ''}" href="${url}">${name}</a>`;
    if (count !== '') html += `<span class="class-count">${count}</span>`;
    html += `</div>`;

    if (!isCollapsed) {
      for (const child of children) {
        html += renderNode(child, depth + 1);
      }
    }
    return html;
  }

  function render() {
    let html = '';
    for (const root of treeData.roots) {
      html += renderNode(root, 0);
    }
    container.innerHTML = html;

    container.querySelectorAll('.tree-arrow[data-node]').forEach(el => {
      el.addEventListener('click', () => {
        const name = el.dataset.node;
        if (collapsed.has(name)) collapsed.delete(name);
        else collapsed.add(name);
        render();
      });
    });
  }

  render();
})();

// ── HAMBURGER ────────────────────────────────────────────────────────────────
(function () {
  const btn     = document.getElementById('hamburger');
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('sidebar-overlay');
  if (!btn || !sidebar) return;

  btn.addEventListener('click', () => {
    sidebar.classList.toggle('open');
    overlay && overlay.classList.toggle('open');
  });

  overlay && overlay.addEventListener('click', () => {
    sidebar.classList.remove('open');
    overlay.classList.remove('open');
  });
})();

// ── TAG ROW WRAP MARGIN ───────────────────────────────────────────────────────
(function () {
  function updateTagDescMargins() {
    document.querySelectorAll('.tag-row').forEach(row => {
      const desc = row.querySelector('.tag-desc');
      if (!desc) return;
      const firstChild = row.firstElementChild;
      const wrapped = desc.offsetTop > firstChild.offsetTop;
      desc.style.marginBottom = wrapped ? '10px' : '';
    });
  }

  updateTagDescMargins();
  window.addEventListener('resize', updateTagDescMargins);
})();

// ── SCROLL ACTIVE INTO VIEW ───────────────────────────────────────────────────
(function () {
  const activeTree   = document.querySelector('#sidebar-tree .tree-link.active');
  const activeMethod = document.querySelector('#method-list .method-item.active');

  if (activeTree) {
    const panel = document.getElementById('sidebar-tree');
    panel.scrollTop = activeTree.offsetTop - panel.clientHeight / 2;
  }

  if (activeMethod) {
    const panel = document.getElementById('method-list');
    panel.scrollTop = activeMethod.offsetTop - panel.clientHeight / 2;
  }
})();
