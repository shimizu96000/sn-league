// attendance.js
// カレンダーの出欠ボタンをクリックしたときに表示を切り替え、サーバーへ更新を送る簡易スクリプト。
// 期待される HTML 構造の例:
// <div class="attendance-item" data-player-id="123" data-date="2025-10-03" data-status="未定">?</div>

(function () {
    function cycleStatus(current) {
        if (!current || current === '未定') return '参加';
        if (current === '参加') return '不参加';
        return '未定';
    }

    document.addEventListener('DOMContentLoaded', function () {
        const items = document.querySelectorAll('.attendance-item');
        items.forEach(item => {
            item.addEventListener('click', async function () {
                const prev = item.getAttribute('data-status') || '未定';
                const next = cycleStatus(prev);

                // UI を先に楽観的に更新
                item.setAttribute('data-status', next);
                item.classList.toggle('active', next !== '未定');

                // サーバー更新用の情報
                const playerId = item.getAttribute('data-player-id');
                const date = item.getAttribute('data-date');

                if (!playerId || !date) {
                    // HTML 側で player-id/date を付与していない場合はここで終了
                    return;
                }

                try {
                    const resp = await fetch('/attendance_update.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ playerId: playerId, date: date, status: next })
                    });
                    const data = await resp.json();
                    if (!resp.ok || (data && data.success === false)) {
                        // 失敗時はロールバック
                        item.setAttribute('data-status', prev);
                        item.classList.toggle('active', prev !== '未定');
                        console.error('attendance update failed', data);
                        alert('出欠の更新に失敗しました');
                    }
                } catch (err) {
                    item.setAttribute('data-status', prev);
                    item.classList.toggle('active', prev !== '未定');
                    console.error(err);
                    alert('通信エラーにより出欠を更新できませんでした');
                }
            });
        });
    });
})();
