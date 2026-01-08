<?php
require_once 'includes/init.php';
$page_title = '運営・協賛';
$current_page = basename(__FILE__);
// load sponsors list if exists (optional JSON)
$sponsors_file = __DIR__ . '/sponsors.json';
sponsors:
$sponsors = [];
if (file_exists($sponsors_file)) {
    $sponsors = json_decode(file_get_contents($sponsors_file), true) ?: [];
}

include 'includes/header.php';
?>
    <main class="management-page">
        <header class="management-hero">
            <h1>運営・協賛</h1>
        </header>

        <section class="management-section management-ops card">
            <h2>運営</h2>
            <div class="card-body">
                <ul class="management-list">
                    <li>運営責任者：清水日向</li>
                    <li>管轄：SNリーグHP管理、試合運営、審判等</li>
                </ul>
            </div>
        </section>

        <section class="management-section management-sponsors card">
            <h2>協賛</h2>
            <div class="card-body">
                <p>当リーグの協賛個人の一覧です。</p>
                <?php if (empty($sponsors)): ?>
                    <div class="sponsor-static-list">
                        <p>雀卓、ドリンク提供：渡邊友喜</p>
                        <p>会場、椅子提供：清水日向</p>
                        <p>配信環境、機材：渡邉琥珀</p>
                    </div>
                <?php else: ?>
                    <div class="sponsors-grid">
                        <?php foreach ($sponsors as $sp): ?>
                            <div class="sponsor-item card small">
                                <div class="sponsor-media">
                                    <?php if (!empty($sp['logo'])): ?>
                                        <img src="<?php echo htmlspecialchars($sp['logo']); ?>" alt="<?php echo htmlspecialchars($sp['name']); ?>" class="sponsor-logo">
                                    <?php endif; ?>
                                </div>
                                <div class="sponsor-body">
                                    <a href="<?php echo htmlspecialchars($sp['url'] ?? '#'); ?>" target="_blank" rel="noopener noreferrer"><?php echo htmlspecialchars($sp['name']); ?></a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>

<?php include 'includes/footer.php';
