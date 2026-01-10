        </main>
    </div>
</div> 
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            
            // 点数計算の外部サイトURL
            const calcURL = 'https://mahjong-calc.livewing.net/';
            window.showCalcConfirmDialog = function() {
                // 確認ダイアログを表示
                if (confirm('外部サイト（麻雀点数計算）に遷移します。よろしいですか？')) {
                    // 新しいウィンドウで点数計算サイトを開く
                    window.open(calcURL, '_blank', 'width=800,height=600');
                }
                return false;
            };
            
            // リアルタイム時計のスクリプト
            const timeElement = document.getElementById('current-time');
            if (timeElement) {
                function updateTime() {
                    const now = new Date();
                    const year = now.getFullYear();
                    const month = String(now.getMonth() + 1).padStart(2, '0');
                    const day = String(now.getDate()).padStart(2, '0');
                    const hours = String(now.getHours()).padStart(2, '0');
                    const minutes = String(now.getMinutes()).padStart(2, '0');
                    const seconds = String(now.getSeconds()).padStart(2, '0');
                    const formattedTime = `${year}年${month}月${day}日 ${hours}:${minutes}:${seconds}`;
                    timeElement.textContent = formattedTime;
                }
                setInterval(updateTime, 1000);
                updateTime();
            }

            // 成績入力フォームのスクリプト
            const scoreForm = document.querySelector('form[action="save_score.php"]');
            if (scoreForm) {
                const scoreInputs = Array.from(document.querySelectorAll('.score-input'));
                const totalScoreSpan = document.getElementById('total-score');
                const submitButton = document.getElementById('submit-button');
                const scoreErrorDiv = document.getElementById('score-error');
                const targetTotal = 1000;

                function updateTotalAndButtonState() {
                    let total = 0;
                    let allInputsHaveValue = true;
                    scoreInputs.forEach(input => {
                        if (input.value.trim() !== '') {
                            total += parseInt(input.value, 10) || 0;
                        } else {
                            allInputsHaveValue = false;
                        }
                    });
                    
                    totalScoreSpan.textContent = total;

                    if (total === targetTotal && allInputsHaveValue) {
                        submitButton.disabled = false;
                        scoreErrorDiv.style.display = 'none';
                    } else {
                        submitButton.disabled = true;
                        if (allInputsHaveValue && total !== targetTotal) {
                            scoreErrorDiv.style.display = 'block';
                        } else {
                            scoreErrorDiv.style.display = 'none';
                        }
                    }
                }

                function autoFillLastScore() {
                    let total = 0;
                    let filledCount = 0;
                    let emptyInput = null;

                    scoreInputs.forEach(input => {
                        if (input.value.trim() !== '') {
                            total += parseInt(input.value, 10) || 0;
                            filledCount++;
                        } else {
                            emptyInput = input;
                        }
                    });

                    if (filledCount === 3 && emptyInput) {
                        const lastScore = targetTotal - total;
                        emptyInput.value = lastScore;
                        updateTotalAndButtonState();
                    }
                }

                scoreInputs.forEach(input => {
                    input.addEventListener('input', updateTotalAndButtonState);
                });

                scoreInputs.forEach(input => {
                    input.addEventListener('blur', autoFillLastScore);
                });

                updateTotalAndButtonState();
            }
            
            // モーダルスクリプト（カレンダーページ用）
            const modal = document.getElementById('attendance-modal');
            const openBtn = document.getElementById('open-modal-btn');
            const closeBtn = document.getElementById('close-modal-btn');
            // Ensure modal starts in compact mode to avoid covering calendar
            if (modal) {
                modal.classList.add('compact');
                // add a toggle button to expand/collapse
                if (!modal.querySelector('.toggle-btn')) {
                    const tb = document.createElement('button');
                    tb.type = 'button';
                    tb.className = 'toggle-btn';
                    tb.textContent = '展開';
                    tb.addEventListener('click', function() {
                        if (modal.classList.contains('compact')) {
                            modal.classList.remove('compact');
                            tb.textContent = '簡易表示';
                        } else {
                            modal.classList.add('compact');
                            tb.textContent = '展開';
                        }
                    });
                    // insert before close button
                    modal.insertBefore(tb, modal.firstChild);
                }
            }
            if(openBtn && modal) { openBtn.onclick = function() { modal.style.display = 'flex'; modal.classList.add('compact'); } }
            if(closeBtn && modal) { closeBtn.onclick = function() { modal.style.display = 'none'; selectMode = false; document.querySelector('.container')?.classList.remove('select-mode'); const selectionInstruction = document.getElementById('selection-instruction'); if (selectionInstruction) selectionInstruction.style.display = 'none'; clearSelection(); } }
            
            // 非同期で出欠データを取得してカレンダーに反映
            (function fetchAttendanceAsync() {
                const attendanceUrl = 'attendance_api.php';
                fetch(attendanceUrl, {cache: 'no-store'})
                    .then(res => res.json())
                    .then(data => {
                        // data は [ [date, player, status], ... ] の形式を想定
                        if (!Array.isArray(data)) return;
                        const map = {};
                        data.forEach((row, idx) => {
                            if (!Array.isArray(row) || idx === 0) return; // 0行目はヘッダー
                            let dateStr = String(row[0] || '').trim();
                            const player = row[1] || '';
                            const status = row[2] || '';
                            if (!dateStr) return;

                            // 日付文字列を JST の 'Y-n-j' 形式に正規化する
                            let dayKey = null;
                            // ISO タイムスタンプ（例: 2025-10-25T15:00:00.000Z）や他の形式を扱う
                            try {
                                const dt = new Date(dateStr);
                                // Intl を使って JST の年/月/日を確実に取得
                                const dtf = new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Tokyo' });
                                // en-CA フォーマットは YYYY-MM-DD
                                const formatted = dtf.format(dt); // 例: '2025-10-26'
                                if (formatted && formatted.match(/^\d{4}-\d{2}-\d{2}$/)) {
                                    // remove leading zeros from month/day to match server-side keys like Y-n-j
                                    const parts = formatted.split('-');
                                    dayKey = parts[0] + '-' + String(parseInt(parts[1],10)) + '-' + String(parseInt(parts[2],10));
                                }
                            } catch (e) {
                                // ignore
                            }
                            // 単純な Y-M-D 形式の場合
                            if (!dayKey && dateStr.match(/^\d{4}-\d{1,2}-\d{1,2}$/)) {
                                dayKey = dateStr;
                            }
                            if (!dayKey) {
                                // 最終手段: Date.parse
                                const dt2 = new Date(dateStr);
                                if (!isNaN(dt2.getTime())) {
                                    const jstMs = dt2.getTime() + (9 * 60 * 60 * 1000);
                                    const jst = new Date(jstMs);
                                    dayKey = jst.getUTCFullYear() + '-' + (jst.getUTCMonth()+1) + '-' + jst.getUTCDate();
                                }
                            }
                            if (!dayKey) return;

                            map[dayKey] = map[dayKey] || {};
                            map[dayKey][player] = status;
                        });

                        console.log('attendance map keys:', Object.keys(map).slice(0,20));
                        // td[data-date] を走査して該当日の出欠を描画
                        document.querySelectorAll('td[data-date]').forEach(td => {
                            const dayKey = td.getAttribute('data-date');
                            if (!dayKey) return;
                            const attendanceList = map[dayKey];
                            const attendanceContainer = td.querySelector('.attendance-list');
                            if (!attendanceContainer) return;
                            // update existing children based on participants
                            Array.from(attendanceContainer.children).forEach(child => {
                                const title = child.getAttribute('title');
                                if (attendanceList && attendanceList[title]) {
                                    const status = attendanceList[title];
                                    child.className = 'attendance-item status-' + status;
                                    child.setAttribute('data-status', status);
                                }
                            });
                        });
                    })
                    .catch(err => console.log('attendance fetch err', err));
            })();

            // カレンダーの出欠アイテムをクリックしたらモーダルを開く（イベント委譲）
            // 日付セルのクリックで選択/解除（複数選択対応） - ただし選択モードが有効な場合のみ
            let selectMode = false;
            const selectedDates = new Set();

            function clearSelection() {
                selectedDates.clear();
                document.querySelectorAll('td.selected-date').forEach(el => el.classList.remove('selected-date'));
                const display = document.getElementById('selected-dates-display');
                const hiddenInput = document.getElementById('attendance-dates');
                if (display) display.textContent = '';
                if (hiddenInput) hiddenInput.value = '';
            }

            // 日付セルのクリック: 選択モードのときのみ反応
            document.querySelectorAll('td[data-date]').forEach(td => {
                td.addEventListener('click', function(e) {
                    // クリックが .attendance-item からのものであれば handled elsewhere
                    if (e.target.closest('.attendance-item')) return;
                    if (!selectMode) return; // 選択モードでなければ何もしない
                    const date = td.getAttribute('data-date');
                    if (!date) return;
                    if (selectedDates.has(date)) {
                        selectedDates.delete(date);
                        td.classList.remove('selected-date');
                    } else {
                        selectedDates.add(date);
                        td.classList.add('selected-date');
                    }
                    // 更新: 選択日表示
                    const display = document.getElementById('selected-dates-display');
                    const hiddenInput = document.getElementById('attendance-dates');
                    if (display && hiddenInput) {
                        const arr = Array.from(selectedDates).sort();
                        display.textContent = arr.join(', ');
                        hiddenInput.value = arr.join(',');
                    }
                });
            });

            // 出欠アイテムクリックで即モーダルを開き、選択日が未選択ならその日を選択状態にする
            document.addEventListener('click', function(e) {
                const target = e.target.closest('.attendance-item');
                if (!target) return;
                const td = target.closest('td[data-date]');
                if (!td) return;
                const date = td.getAttribute('data-date');
                const playerName = target.getAttribute('title') || target.textContent;
                // 選択セットに日が無ければ追加（単発選択）
                if (!selectedDates.has(date)) {
                    // clear previous
                    clearSelection();
                    selectedDates.add(date);
                    td.classList.add('selected-date');
                    const display = document.getElementById('selected-dates-display');
                    const hiddenInput = document.getElementById('attendance-dates');
                    if (display && hiddenInput) {
                        display.textContent = date;
                        hiddenInput.value = date;
                    }
                }
                if (modal) {
                    modal.style.display = 'flex';
                    const playerSelect = document.getElementById('player-select');
                    if (playerSelect) playerSelect.value = playerName;
                }
            });

            // 「出欠を登録する」ボタンの動作: 1回目は選択モードを有効化、2回目はモーダルを開いて登録処理へ
            const openModalBtn = document.getElementById('open-modal-btn');
            const selectionInstruction = document.getElementById('selection-instruction');
            if (openModalBtn) {
                openModalBtn.addEventListener('click', function(e) {
                    // 既存の選択をクリアしてモーダルを開き、カレンダーで日付を選べる状態にする
                    clearSelection();
                    selectMode = true;
                    document.querySelector('.container')?.classList.add('select-mode');
                    if (selectionInstruction) selectionInstruction.style.display = 'block';
                    openModalBtn.textContent = '選択モード: 日付を選択してください';
                    if (modal) modal.style.display = 'flex';
                });
            }

            // モーダルを閉じたら選択モードを解除して選択をクリア
            if (closeBtn && modal) {
                closeBtn.onclick = function() {
                    modal.style.display = 'none';
                    // 選択モード解除
                    selectMode = false;
                    document.querySelector('.container')?.classList.remove('select-mode');
                    if (selectionInstruction) selectionInstruction.style.display = 'none';
                    if (openModalBtn) openModalBtn.textContent = '出欠を登録する';
                    clearSelection();
                };
            }
            if(modal) { window.onclick = function(event) { if (event.target == modal) { modal.style.display = 'none'; selectMode = false; document.querySelector('.container')?.classList.remove('select-mode'); if (selectionInstruction) selectionInstruction.style.display = 'none'; if (openModalBtn) openModalBtn.textContent = '出欠を登録する'; clearSelection(); } } }

            // attendance form を AJAX で送信して即座に UI を更新する
            const attendanceForm = document.getElementById('attendance-form');
            if (attendanceForm) {
                attendanceForm.addEventListener('submit', function(evt) {
                    evt.preventDefault();
                    // dates がセットされていることを確認
                    const datesField = attendanceForm.querySelector('input[name="dates"]');
                    if (!datesField || !datesField.value || datesField.value.trim() === '') {
                        alert('先にカレンダー上で登録する日付を選択してください。');
                        return;
                    }
                    const formData = new FormData(attendanceForm);
                    // fetch で POST
                    // デバッグ: 送信するフォームデータをログ
                    try {
                        const debugPairs = [];
                        for (const pair of formData.entries()) debugPairs.push(pair);
                        console.log('attendanceForm submit data:', debugPairs);
                    } catch (e) { console.log('formData debug err', e); }

                    fetch(attendanceForm.action, {
                        method: 'POST',
                        body: formData,
                        cache: 'no-store',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        }
                    }).then(res => res.json()).then(result => {
                        if (result && result.success) {
                            // 成功時: フロントの attendance 表示を更新
                            const updated = result.updated || [];
                            // updated は [{date: 'Y-n-j', player: '名前', status: '参加'},...]
                            updated.forEach(item => {
                                const td = document.querySelector('td[data-date="' + item.date + '"]');
                                if (!td) return;
                                const attendanceItems = Array.from(td.querySelectorAll('.attendance-item'));
                                attendanceItems.forEach(ai => {
                                    if ((ai.getAttribute('title') || ai.textContent) === item.player) {
                                        ai.className = 'attendance-item status-' + item.status;
                                        ai.setAttribute('data-status', item.status);
                                    }
                                });
                            });
                            // キャッシュはサーバがクリア済みとして、モーダル閉じ・選択解除
                            modal.style.display = 'none';
                            selectMode = false;
                            document.querySelector('.container')?.classList.remove('select-mode');
                            if (selectionInstruction) selectionInstruction.style.display = 'none';
                            if (openModalBtn) openModalBtn.textContent = '出欠を登録する';
                            clearSelection();
                            // フィードバック表示
                            function showFeedback(msg, isError) {
                                let fb = document.getElementById('attendance-feedback');
                                if (!fb) {
                                    fb = document.createElement('div');
                                    fb.id = 'attendance-feedback';
                                    fb.style.position = 'fixed';
                                    fb.style.right = '18px';
                                    fb.style.bottom = '80px';
                                    fb.style.padding = '10px 14px';
                                    fb.style.borderRadius = '6px';
                                    fb.style.zIndex = '110';
                                    fb.style.boxShadow = '0 4px 12px rgba(0,0,0,0.12)';
                                    document.body.appendChild(fb);
                                }
                                fb.textContent = msg;
                                fb.style.background = isError ? '#f8d7da' : '#d1e7dd';
                                fb.style.color = isError ? '#842029' : '#0f5132';
                                fb.style.opacity = '1';
                                setTimeout(() => { fb.style.transition = 'opacity 0.4s'; fb.style.opacity = '0'; }, 2500);
                            }
                            showFeedback('保存が完了しました', false);
                        } else {
                            alert('出欠の登録に失敗しました');
                            console.log('attendance update failed', result);
                        }
                    }).catch(err => {
                        console.error('attendance submit err', err);
                        alert('通信エラーが発生しました');
                    });
                });
            }

            // attendance_register.php の date input を Y-n-j 形式に変換して送信
            const standaloneForm = document.querySelector('form[action="update_attendance.php"]');
            if (standaloneForm && window.location.pathname.endsWith('attendance_register.php')) {
                standaloneForm.addEventListener('submit', function(e) {
                    const dateInput = document.getElementById('date-input');
                    if (dateInput && dateInput.value) {
                        // HTML date は YYYY-MM-DD
                        const parts = dateInput.value.split('-');
                        if (parts.length === 3) {
                            const y = parts[0];
                            const m = String(parseInt(parts[1], 10));
                            const d = String(parseInt(parts[2], 10));
                            // update_attendance はカンマ区切りの 'Y-n-j' を期待している
                            // hidden input 名は dates なので上書き
                            // フォーム内に同名 hidden が無ければ create
                            let hidden = standaloneForm.querySelector('input[name="dates"]');
                            if (!hidden) {
                                hidden = document.createElement('input');
                                hidden.type = 'hidden';
                                hidden.name = 'dates';
                                standaloneForm.appendChild(hidden);
                            }
                            hidden.value = y + '-' + m + '-' + d;
                        }
                    }
                });
            }
            
            // 【変更】jQuery UIのSortableの初期化コードを削除
            // if (typeof jQuery !== 'undefined' && typeof jQuery.ui !== 'undefined') {
            //     $(".sortable-list").sortable({
            //         placeholder: "ui-state-highlight",
            //         update: function(event, ui) {
            //             var newOrder = $(this).sortable('toArray', {attribute: 'data-player-name'});
            //             $('input[name="player_order"]').val(newOrder.join(','));
            //         }
            //     }).disableSelection();
            // }
        });
    </script>
</body>
</html>