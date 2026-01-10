<?php
require_once 'includes/init.php';
$page_title = '麻雀クイズ';
$current_page = basename(__FILE__);

// リセット処理
if (isset($_GET['reset'])) {
    unset($_SESSION['answered_questions']);
    unset($_SESSION['correct_answers']);
    unset($_SESSION['quiz_completed']);
    header('Location: mahjong_quiz.php');
    exit;
}

// セッション変数の初期化
if (!isset($_SESSION['answered_questions']) || !is_array($_SESSION['answered_questions'])) {
    $_SESSION['answered_questions'] = [];
}
if (!isset($_SESSION['correct_answers']) || !is_array($_SESSION['correct_answers'])) {
    $_SESSION['correct_answers'] = [];
}

// クイズデータをJSONファイルから読み込む
$quiz_file = __DIR__ . '/data/quiz_data.json';
$quizzes = [];
if (file_exists($quiz_file)) {
    $quizzes = json_decode(file_get_contents($quiz_file), true) ?? [];
}

// クイズが無い場合のフォールバック
if (empty($quizzes)) {
    $quizzes = [
        [
            'question' => 'クイズデータが見つかりません',
            'options' => ['はい', 'いいえ'],
            'answer' => 0,
            'explanation' => '管理画面でクイズを追加してください。'
        ]
    ];
}

// クイズの結果を処理
$score = 0;
$submitted = false;
$answers = [];
$explanations = [];

// Mode handling: main menu, all (multi-question), single (one random question)
$singleMode = isset($_GET['single']);
$allMode = isset($_GET['all']);

// Handle multi-question submit (existing behavior)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['answers'])) {
    $submitted = true;
    $answers = $_POST['answers'];
    foreach ($answers as $index => $answer) {
        if (isset($quizzes[$index]) && (int)$answer === $quizzes[$index]['answer']) {
            $score++;
        }
        $explanations[$index] = $quizzes[$index]['explanation'];
    }
}

// Handle single-question answer submit
$singleResult = null; // will hold result array when answering single question
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['single_answer'])) {
    $qIndex = isset($_POST['q']) ? (int)$_POST['q'] : null;
    $given = (int)$_POST['single_answer'];
    if ($qIndex !== null && isset($quizzes[$qIndex])) {
        $quiz = $quizzes[$qIndex];
        // 選択された選択肢のインデックスから正誤を判定
        $displayedOptions = [];
        for ($i = 0; $i < 4; $i++) {
            if (isset($_POST['opt' . $i])) {
                $displayedOptions[] = $_POST['opt' . $i];
            }
        }
        $correctText = $quiz['options'][$quiz['answer']];
        $correctIndexInDisplay = array_search($correctText, $displayedOptions);
        $isCorrect = ($given === $correctIndexInDisplay);
        
        $singleResult = [
            'index' => $qIndex,
            'quiz' => $quiz,
            'given' => $given,
            'isCorrect' => $isCorrect,
        ];
        
        // ここで正解を記録
        if (!isset($_SESSION['answered_questions'])) {
            $_SESSION['answered_questions'] = [];
        }
        if (!isset($_SESSION['correct_answers'])) {
            $_SESSION['correct_answers'] = [];
        }
        
        if (!in_array($qIndex, $_SESSION['answered_questions'])) {
            $_SESSION['answered_questions'][] = $qIndex;
            if ($isCorrect) {
                $_SESSION['correct_answers'][] = $qIndex;
            }
        }
    }
}

include 'includes/header.php';
?>

<h1>麻雀クイズ</h1>
<p class="catchphrase-small">「常に打点を高めよ」</p>

<?php
// Display modes:
// - main menu: neither single nor all
// - single mode: ?single=1 shows one random question (or ?single=1&q=N)
// - all mode: ?all=1 shows full multi-question form

// 進捗状況の初期化とリセット
if (!isset($_SESSION['answered_questions']) || !is_array($_SESSION['answered_questions'])) {
    $_SESSION['answered_questions'] = [];
}

if ($singleMode) {
    // Check for quiz completion
    if (isset($_SESSION['quiz_completed']) && $_SESSION['quiz_completed']) {
        unset($_SESSION['answered_questions']);
        unset($_SESSION['quiz_completed']);
        header('Location: mahjong_quiz.php');
        exit;
    }

    // Single-question flow
    if ($singleResult !== null) {
        // show result for the answered single question
        $q = $singleResult['index'];
        $quiz = $singleResult['quiz'];
        $given = $singleResult['given'];

        // Mark question as answered and track correct answers
        if (!in_array($q, $_SESSION['answered_questions'])) {
            $_SESSION['answered_questions'][] = $q;
            if ($singleResult['isCorrect']) {
                $_SESSION['correct_answers'][] = $q;
            }
        }

        // Check if all questions have been answered
        $remaining = array_diff(range(0, count($quizzes) - 1), $_SESSION['answered_questions']);
        if (empty($remaining)) {
            $_SESSION['quiz_completed'] = true;
        }
        // 元の選択肢の順序と解説を復元
        $displayOptions = [];
        $optionExplanations = [];
        for ($i = 0; $i < 4; $i++) {
            if (isset($_POST['opt' . $i])) {
                $displayOptions[$i] = $_POST['opt' . $i];
                $optionExplanations[$i] = isset($_POST['explanation' . $i]) ? $_POST['explanation' . $i] : '';
            }
        }

        // 正解の選択肢のインデックスを取得
        $correctText = $quiz['options'][$quiz['answer']];
        $correctIndexInDisplay = array_search($correctText, $displayOptions);
        ?>
        <div class="section">
            <h2>問題の結果</h2>
            <div class="quiz-item <?php echo $singleResult['isCorrect'] ? 'correct' : 'incorrect'; ?>">
                <h3>問題</h3>
                <p class="quiz-question"><?php echo htmlspecialchars($quiz['question']); ?></p>
                <div class="quiz-options">
                    <?php foreach ($displayOptions as $idx => $opt): ?>
                        <div class="quiz-option" style="padding:6px;">
                            <?php if ($idx === $given): ?><strong>あなたの回答 ▶ </strong><?php endif; ?>
                            <?php if ($opt === $quiz['options'][$quiz['answer']]): ?>
                                <span style="color:green;font-weight:bold;">正解: <?php echo htmlspecialchars($opt); ?></span>
                            <?php else: ?>
                                <span><?php echo htmlspecialchars($opt); ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="quiz-explanation">
                    <?php
                    // 選択肢ごとの解説を表示
                    $hasIndividualExplanations = false;
                    foreach ($displayOptions as $idx => $opt) {
                        if (!empty($optionExplanations[$idx])) {
                            if (!$hasIndividualExplanations) {
                                echo '<h4>選択肢ごとの解説</h4>';
                                $hasIndividualExplanations = true;
                            }
                            $isCorrect = ($opt === $correctText);
                            echo '<div class="option-explanation' . ($isCorrect ? ' correct-option' : '') . '">';
                            echo '<strong>' . ($idx + 1) . '. ' . htmlspecialchars($opt) . '</strong>';
                            echo '<div class="explanation-detail">' . nl2br(htmlspecialchars($optionExplanations[$idx])) . '</div>';
                            echo '</div>';
                        }
                    }

                    // 問題全体の解説を表示
                    if (!empty($quiz['explanation'])) {
                        if ($hasIndividualExplanations) {
                            echo '<hr style="margin: 15px 0;">';
                        }
                        echo '<h4>問題の解説</h4>';
                        echo '<div class="overall-explanation">' . nl2br(htmlspecialchars($quiz['explanation'])) . '</div>';
                    }
                    ?>
                </div>
            </div>
            <div style="margin-top:12px;">
                <a href="mahjong_quiz.php?single=1" class="btn">次の問題</a>
                <a href="mahjong_quiz.php" class="btn">メニューに戻る</a>
            </div>
        </div>
        <?php
    } else {
        // Get available questions (not answered yet)
        // セッション配列の初期化を確認
        if (!isset($_SESSION['answered_questions']) || !is_array($_SESSION['answered_questions'])) {
            $_SESSION['answered_questions'] = [];
        }
        
        // 回答済み問題と利用可能な問題を取得
        $answeredQuestions = $_SESSION['answered_questions'];
        $availableQuestions = array_diff(range(0, count($quizzes) - 1), $answeredQuestions);
        
        // スキップ処理
        if (isset($_GET['skip'])) {
            $skipIndex = (int)$_GET['skip'];
            if (!in_array($skipIndex, $answeredQuestions)) {
                $_SESSION['answered_questions'][] = $skipIndex;
            }
            header('Location: mahjong_quiz.php?single=1');
            exit;
        }
        
        // 全問題完了チェック
        if (empty($availableQuestions)) {
            $_SESSION['quiz_completed'] = true;
            header('Location: mahjong_quiz.php');
            exit;
        }
        
        // present a random question from available ones
        if (isset($_GET['q'])) {
            $qIndex = (int)$_GET['q'];
            // If requested question is already answered, redirect to a random one
            if (in_array($qIndex, $answeredQuestions)) {
                header('Location: mahjong_quiz.php?single=1');
                exit;
            }
        } else {
            $availableArray = array_values($availableQuestions);
            $qIndex = $availableArray[array_rand($availableArray)];
        }
        $quiz = $quizzes[$qIndex];

        // Use the quiz's own options for single-mode so per-option explanations map reliably
        $correctText = $quiz['options'][$quiz['answer']];
        $displayOptions = $quiz['options'];
        // ensure exactly 4 entries (pad with empty strings if needed)
        while (count($displayOptions) < 4) $displayOptions[] = '';
        $displayOptions = array_slice($displayOptions, 0, 4);
        shuffle($displayOptions);

        // prepare per-option explanations aligned to the displayed option texts
        $optionExplanations = [];
        $perOptionMap = [];
        if (isset($quiz['explanations']) && is_array($quiz['explanations'])) {
            foreach ($quiz['options'] as $i => $optText) {
                if (isset($quiz['explanations'][$i]) && !empty($quiz['explanations'][$i])) {
                    $perOptionMap[$optText] = $quiz['explanations'][$i];
                }
            }
        }
        foreach ($displayOptions as $opt) {
            if ($opt === '') {
                $optionExplanations[] = '';
            } elseif (isset($perOptionMap[$opt])) {
                $optionExplanations[] = $perOptionMap[$opt];
            } else {
                $optionExplanations[] = '';
            }
        }
        $correctIndexInDisplay = array_search($correctText, $displayOptions, true);
        ?>
        <div class="section">
            <h2>一問ずつ挑戦</h2>
            <div class="quiz-item">
                <h3>問題</h3>
                <div class="quiz-question">
                    <?php if (strpos($quiz['question'], '[') !== false): ?>
                        <div class="mahjong-tiles-row">
                            <div class="mahjong-tiles">
                                <?php echo htmlspecialchars($quiz['question']); ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <p class="quiz-question-text"><?php echo htmlspecialchars($quiz['question']); ?></p>
                    <?php endif; ?>
                <div class="quiz-options" id="single-quiz-<?php echo $qIndex; ?>">
                    <?php foreach ($displayOptions as $idx => $opt): ?>
                        <?php $exText = $optionExplanations[$idx] ?? ''; ?>
                        <button type="button"
                                class="single-option-btn quiz-option"
                                data-idx="<?php echo $idx; ?>"
                                data-correct="<?php echo ($idx === $correctIndexInDisplay) ? '1' : '0'; ?>"
                                data-explanation="<?php echo htmlspecialchars($exText, ENT_QUOTES); ?>">
                            <?php if (strpos($opt, '[') !== false): ?>
                                <div class="mahjong-tiles-row">
                                    <div class="mahjong-tiles"><?php echo htmlspecialchars($opt); ?></div>
                                </div>
                            <?php else: ?>
                                <?php echo htmlspecialchars($opt); ?>
                            <?php endif; ?>
                        </button>
                    <?php endforeach; ?>
                </div>
                <div class="quiz-explanation" id="single-explain" style="margin-top:12px;display:none;"></div>
                <div class="quiz-explanation" id="single-explain-all" style="margin-top:12px;display:none;"></div>
                <!-- result overlay holder -->
                <div id="result-overlay-holder"></div>
                <div class="quiz-progress">
                    進捗: <?php echo count($_SESSION['answered_questions']); ?> / <?php echo count($quizzes); ?>問目 
                    （正解: <?php echo count($_SESSION['correct_answers']); ?>問）
                </div>
                <div id="single-controls" style="margin-top:12px;">
                    <a href="mahjong_quiz.php?single=1" class="btn">次の問題へ</a>
                    <a href="mahjong_quiz.php?single=1&skip=<?php echo $qIndex; ?>" class="btn btn-skip">スキップ</a>
                    <a href="mahjong_quiz.php" class="btn">メニューに戻る</a>
                </div>
            </div>
        
        <script>
            (function(){
                const container = document.getElementById('single-quiz-<?php echo $qIndex; ?>');
                const explain = document.getElementById('single-explain');
                const explainAll = document.getElementById('single-explain-all');
                const controls = document.getElementById('single-controls');
                let answered = false;
                if (!container) return;

                function submitAnswer(isCorrect, selectedIdx) {
                    // POSTリクエストを作成
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.style.display = 'none';

                    // 必要なパラメータを追加
                    const inputs = {
                        'single_answer': selectedIdx,
                        'q': '<?php echo $qIndex; ?>',
                    };

                    for (const [key, value] of Object.entries(inputs)) {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = key;
                        input.value = value;
                        form.appendChild(input);
                    }

                    // オプションの値と解説を送信
                    container.querySelectorAll('.single-option-btn').forEach((btn, idx) => {
                        // オプションの値を送信
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'opt' + idx;
                        input.value = btn.textContent.trim();
                        form.appendChild(input);

                        // 解説を送信
                        const expInput = document.createElement('input');
                        expInput.type = 'hidden';
                        expInput.name = 'explanation' + idx;
                        expInput.value = btn.getAttribute('data-explanation') || '';
                        form.appendChild(expInput);
                    });

                    // フォームを追加して送信
                    document.body.appendChild(form);
                    form.submit();
                }

                container.querySelectorAll('.single-option-btn').forEach(btn => {
                    btn.addEventListener('click', function(){
                        if (answered) return;
                        answered = true;
                        const isCorrect = this.getAttribute('data-correct') === '1';
                        const ex = this.getAttribute('data-explanation') || '';
                        // style selected
                        this.classList.add(isCorrect ? 'selected-correct' : 'selected-incorrect');
                        // highlight correct option and disable all
                        container.querySelectorAll('.single-option-btn').forEach(b => {
                            b.disabled = true;
                            if (b.getAttribute('data-correct') === '1') b.classList.add('correct-option');
                        });
                        // show the clicked option's explanation (as primary)
                        explain.style.display = 'block';
                        explain.innerHTML = ex ? (ex.replace(/\n/g,'<br>')) : (isCorrect ? '正解です。' : '不正解です。');

                        // show full-screen overlay and badge for visual feedback
                        try {
                            const overlayHolder = document.getElementById('result-overlay-holder');
                            // create overlay
                            const ov = document.createElement('div');
                            ov.className = 'result-overlay ' + (isCorrect ? 'correct' : 'incorrect');
                            // create centered badge
                            const badge = document.createElement('div');
                            badge.className = 'result-badge ' + (isCorrect ? 'correct' : 'incorrect');
                            badge.style.position = 'fixed';
                            badge.style.left = '50%';
                            badge.style.top = '18%';
                            badge.style.transform = 'translateX(-50%)';
                            badge.style.zIndex = 2010;
                            badge.textContent = isCorrect ? '正解！' : '不正解';
                            overlayHolder.appendChild(ov);
                            overlayHolder.appendChild(badge);
                            // remove after animation
                            setTimeout(()=>{ try { overlayHolder.removeChild(ov); overlayHolder.removeChild(badge); } catch(e){} }, 1000);
                        } catch(e) { console.error(e); }
                        // show all options' explanations and overall explanation
                        let html = '';
                        let hasIndividualExplanations = false;
                        
                        // 選択肢ごとの解説を収集
                        const optionsWithExplanations = [];
                        container.querySelectorAll('.single-option-btn').forEach((b, i) => {
                            const text = b.textContent || b.innerText;
                            const e = b.getAttribute('data-explanation');
                            if (e && e.trim() !== '') {  // 空文字でない場合のみ追加
                                optionsWithExplanations.push({
                                    text: text,
                                    explanation: e,
                                    index: i,
                                    isCorrect: b.getAttribute('data-correct') === '1'
                                });
                            }
                        });

                        // 選択肢ごとの解説がある場合のみ表示セクションを作成
                        if (optionsWithExplanations.length > 0) {
                            html += '<h4>選択肢ごとの解説</h4>';
                            optionsWithExplanations.forEach(opt => {
                                html += '<div class="option-explanation' + (opt.isCorrect ? ' correct-option' : '') + '">';
                                html += '<strong>' + (opt.index + 1) + '. ' + opt.text + '</strong>';
                                html += '<div class="explanation-detail">' + opt.explanation.replace(/\n/g,'<br>') + '</div>';
                                html += '</div>';
                            });
                            hasIndividualExplanations = true;
                        }

                        // 問題全体の解説を表示
                        const overall = <?php echo json_encode($quiz['explanation'] ?? ''); ?>;
                        if (overall && overall.trim() !== '') {
                            if (hasIndividualExplanations) {
                                html += '<hr style="margin: 15px 0;">'; // 区切り線
                            }
                            html += '<h4>問題の解説</h4><div class="overall-explanation">' + overall.replace(/\n/g,'<br>') + '</div>';
                        }
                        explainAll.style.display = 'block';
                        explainAll.innerHTML = html;
                        controls.style.display = 'block';

                        // 解答をサーバーに送信
                        const selectedIdx = parseInt(this.getAttribute('data-idx'));
                        setTimeout(() => submitAnswer(isCorrect, selectedIdx), 800);
                    });
                });
            })();
        </script>
        </div>
        <?php
    }
} else {
    // not single mode: show either main menu (no allMode) or all-mode multi-question form
    if (!$allMode && !$submitted) {
        // main menu
        ?>
        <?php if (isset($_SESSION['quiz_completed']) && $_SESSION['quiz_completed']): ?>
            <div class="quiz-complete">
                <h2>クイズ完了！</h2>
                <p>全ての問題を解答しました。お疲れ様でした！</p>
                <div class="navigation-buttons">
                    <a href="mahjong_quiz.php?reset=1" class="btn">最初からやり直す</a>
                    <a href="mahjong_quiz.php" class="btn">クイズホームに戻る</a>
                </div>
            </div>
        <?php else: ?>
            <div class="section">
                <h2>クイズに挑戦</h2>
                <p>遊び方を選んでください。</p>
                <?php if (isset($_SESSION['answered_questions']) && !empty($_SESSION['answered_questions'])): ?>
                <div class="quiz-progress">
                    進捗: <?php echo count($_SESSION['answered_questions']); ?> / <?php echo count($quizzes); ?>問解答済み
                    （正解: <?php echo count($_SESSION['correct_answers']); ?>問）
                    <div style="margin-top:8px;">
                        <a href="mahjong_quiz.php?reset=1" class="btn btn-small">進捗をリセット</a>
                    </div>
                </div>
                <?php endif; ?>
        <?php endif; ?>
            <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:15px;">
                <a href="mahjong_quiz.php?single=1" class="btn">一問ずつ挑戦</a>
                <a href="mahjong_quiz.php?all=1" class="btn">全問に挑戦（従来）</a>
            </div>
        </div>
        <?php
    } elseif ($allMode && !$submitted) {
        // original multi-question form
        ?>
        <script>
        function selectAnswer(button, questionIndex, optionIndex) {
            // 既に選択済みの場合は何もしない
            if (button.disabled) return;
            
            const questionContainer = button.closest('.quiz-item');
            const parent = button.parentElement;
            const buttons = parent.querySelectorAll('.single-option-btn');
            const correctAnswer = parseInt(questionContainer.dataset.correctAnswer);
            const explanation = questionContainer.dataset.explanation;
            const optionExplanations = JSON.parse(questionContainer.dataset.optionExplanations);
            const isCorrect = optionIndex === correctAnswer;
            
            // 他のボタンの選択状態をリセット
            buttons.forEach(btn => {
                btn.classList.remove('selected-correct', 'selected-incorrect');
                btn.disabled = true;
            });
            
            // クリックされたボタンを選択状態にする
            button.classList.add(isCorrect ? 'selected-correct' : 'selected-incorrect');
            
            // 正解のボタンを表示
            buttons.forEach((btn, idx) => {
                if (idx === correctAnswer) {
                    btn.classList.add('correct-option');
                }
            });

            // 解説を表示
            let explanationDiv = questionContainer.querySelector('.quiz-explanation');
            if (!explanationDiv) {
                explanationDiv = document.createElement('div');
                explanationDiv.className = 'quiz-explanation';
                questionContainer.appendChild(explanationDiv);
            }

            // 選択肢ごとの解説を表示するHTML作成
            let html = '<h4>選択肢ごとの解説</h4>';
            buttons.forEach((btn, idx) => {
                const optText = btn.textContent.trim();
                const optExplanation = optionExplanations[idx] || '';
                const isCorrectOpt = idx === correctAnswer;
                const isSelectedOpt = idx === optionIndex;
                
                html += '<div class="option-explanation ' + 
                       (isCorrectOpt ? 'correct-option' : '') + 
                       (isSelectedOpt ? ' selected-option' : '') + 
                       '">';
                html += '<strong>' + (idx + 1) + '. ' + optText + '</strong>';
                if (optExplanation) {
                    html += '<div class="explanation-detail">' + optExplanation.replace(/\n/g, '<br>') + '</div>';
                }
                html += '</div>';
            });

            // 問題全体の解説を追加
            if (explanation) {
                html += '<h4>問題全体の解説</h4>';
                html += '<div class="overall-explanation">' + explanation.replace(/\n/g, '<br>') + '</div>';
            }

            explanationDiv.innerHTML = html;

            // 結果のオーバーレイとバッジを表示
            try {
                const overlayHolder = document.createElement('div');
                overlayHolder.className = 'result-overlay-holder';
                questionContainer.appendChild(overlayHolder);

                // オーバーレイを作成
                const ov = document.createElement('div');
                ov.className = 'result-overlay ' + (isCorrect ? 'correct' : 'incorrect');
                
                // バッジを作成
                const badge = document.createElement('div');
                badge.className = 'result-badge ' + (isCorrect ? 'correct' : 'incorrect');
                badge.style.position = 'fixed';
                badge.style.left = '50%';
                badge.style.top = '18%';
                badge.style.transform = 'translateX(-50%)';
                badge.style.zIndex = 2010;
                badge.textContent = isCorrect ? '正解！' : '不正解';
                
                overlayHolder.appendChild(ov);
                overlayHolder.appendChild(badge);
                
                // アニメーション後に削除
                setTimeout(() => {
                    try {
                        overlayHolder.removeChild(ov);
                        overlayHolder.removeChild(badge);
                    } catch(e) {}
                }, 1000);
            } catch(e) { console.error(e); }
        }
        </script>
        
        <div class="section">
            <h2>クイズに挑戦（全問）</h2>
            <form method="post" class="quiz-form">
                <?php foreach ($quizzes as $index => $quiz): ?>
                    <div class="quiz-item" 
                         data-correct-answer="<?php echo $quiz['answer']; ?>" 
                         data-explanation="<?php echo htmlspecialchars($quiz['explanation']); ?>"
                         data-option-explanations='<?php echo htmlspecialchars(json_encode(isset($quiz['explanations']) ? $quiz['explanations'] : array_fill(0, count($quiz['options']), ""))); ?>'>
                        <h3>問題<?php echo $index + 1; ?></h3>
                        <p class="quiz-question mahjong-tiles"><?php echo htmlspecialchars($quiz['question']); ?></p>
                        <div class="quiz-options">
                            <?php foreach ($quiz['options'] as $optionIndex => $option): ?>
                                <button type="button" 
                                        class="single-option-btn quiz-option" 
                                        onclick="selectAnswer(this, <?php echo $index; ?>, <?php echo $optionIndex; ?>)">
                                    <?php echo htmlspecialchars($option); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <div style="margin-top:12px;"><a href="mahjong_quiz.php" class="btn">メニューに戻る</a></div>
            </form>
        </div>
        <?php
    } else {
        // show results for all-mode submission
        ?>
        <div class="section">
            <h2>クイズ結果</h2>
            <div class="quiz-result">
                <p class="quiz-score">スコア: <?php echo $score; ?> / <?php echo count($quizzes); ?></p>
                <?php foreach ($quizzes as $index => $quiz): ?>
                    <?php 
                    $isCorrect = isset($answers[$index]) && (int)$answers[$index] === $quiz['answer'];
                    $selectedAnswer = isset($answers[$index]) ? (int)$answers[$index] : null;
                    ?>
                    <div class="quiz-item <?php echo $isCorrect ? 'correct' : 'incorrect'; ?>">
                        <h3>問題<?php echo $index + 1; ?></h3>
                        <p class="quiz-question mahjong-tiles"><?php echo htmlspecialchars($quiz['question']); ?></p>
                        <div class="quiz-options">
                            <?php foreach ($quiz['options'] as $optionIndex => $option): ?>
                                <button type="button" 
                                        class="single-option-btn quiz-option <?php 
                                            if ($optionIndex === $selectedAnswer) {
                                                echo $isCorrect ? 'selected-correct' : 'selected-incorrect';
                                            }
                                            if ($optionIndex === $quiz['answer']) {
                                                echo ' correct-option';
                                            }
                                        ?>"
                                        disabled>
                                    <?php echo htmlspecialchars($option); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                        <p class="quiz-explanation"><?php echo nl2br(htmlspecialchars($explanations[$index])); ?></p>
                    </div>
                <?php endforeach; ?>
                <a href="mahjong_quiz.php" class="btn">メニューに戻る</a>
            </div>
        </div>
        <?php
    }
}

include 'includes/footer.php';
?>
