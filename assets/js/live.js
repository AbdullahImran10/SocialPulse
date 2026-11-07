/* SocialPulse live dashboard: polls api/live.php and updates cards + charts */
(function () {
    const cfg = window.LIVE_CFG || {};
    const POLL_MS = cfg.interval || 5000;
    let lastId = null;
    let timer = null;

    const pill = document.getElementById('livePill');
    const pillText = document.getElementById('liveText');
    const feed = document.getElementById('liveFeedList');

    const fmt = (n) => Number(n).toLocaleString();
    const setPill = (on, text) => {
        if (!pill) return;
        pill.classList.toggle('off', !on);
        pillText.textContent = text;
    };

    function updateCards(t) {
        document.querySelectorAll('[data-live]').forEach((el) => {
            const key = el.getAttribute('data-live');
            if (!(key in t)) return;
            const next = fmt(t[key]);
            if (el.textContent.trim() !== next) {
                el.textContent = next;
                el.classList.add('bump');
                setTimeout(() => el.classList.remove('bump'), 900);
            }
        });
    }

    function updateCharts(t, platform) {
        if (window.performanceChart) {
            window.performanceChart.data.datasets[0].data = [
                t.total_views, t.total_likes, t.total_comments, t.total_shares, t.total_reach
            ];
            window.performanceChart.update();
        }
        if (window.platformChart) {
            window.platformChart.data.labels = platform.labels;
            window.platformChart.data.datasets[0].data = platform.views;
            window.platformChart.update();
        }
    }

    function updateFeed(rows, isNewBatch) {
        if (!feed) return;
        feed.innerHTML = '';
        rows.forEach((r, i) => {
            const li = document.createElement('li');
            if (isNewBatch && i === 0) li.className = 'new';
            const left = document.createElement('span');
            left.textContent = r.platform + ' · ' + r.account_name + ' — ' + (r.caption || '(no caption)');
            const right = document.createElement('small');
            right.textContent = fmt(r.views) + ' views · ' + fmt(r.likes) + ' likes · ' + r.recorded_at;
            li.append(left, right);
            feed.appendChild(li);
        });
    }

    async function poll() {
        try {
            const qs = new URLSearchParams({ start_date: cfg.start, end_date: cfg.end });
            const res = await fetch('api/live.php?' + qs, { credentials: 'same-origin', cache: 'no-store' });
            if (res.status === 401) { window.location.href = 'login.php'; return; }
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const d = await res.json();

            updateCards(d.totals);
            updateCharts(d.totals, d.platform);
            updateFeed(d.activity, lastId !== null && d.last_id !== lastId);
            lastId = d.last_id;
            setPill(true, 'LIVE · ' + d.server_time);
        } catch (err) {
            setPill(false, 'OFFLINE · retrying');
        }
    }

    function start() { poll(); timer = setInterval(poll, POLL_MS); }
    function stop() { clearInterval(timer); timer = null; }

    // don't hammer the server from background tabs
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) stop(); else if (!timer) start();
    });

    start();
})();
