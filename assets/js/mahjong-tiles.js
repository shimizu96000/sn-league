// 麻雀牌文字列をHTML要素に変換するクラス
class MahjongTileRenderer {
    static typeMap = {
        // 画像ファイル名は短縮形 (m,p,s,z) を使用しているため、それに合わせる
        'm': 'm',    // 萬子 (m)
        'p': 'p',    // 筒子 (p)
        's': 's',    // 索子 (s)
        'z': 'z'     // 字牌  (z)
    };

    static tileMap = {
        // 萬子
        'm1': '一萬', 'm2': '二萬', 'm3': '三萬', 'm4': '四萬', 'm5': '五萬',
        'm6': '六萬', 'm7': '七萬', 'm8': '八萬', 'm9': '九萬',
        // 筒子
        'p1': '一筒', 'p2': '二筒', 'p3': '三筒', 'p4': '四筒', 'p5': '五筒',
        'p6': '六筒', 'p7': '七筒', 'p8': '八筒', 'p9': '九筒',
        // 索子
        's1': '一索', 's2': '二索', 's3': '三索', 's4': '四索', 's5': '五索',
        's6': '六索', 's7': '七索', 's8': '八索', 's9': '九索',
        // 字牌（1-4: 東南西北, 5-7: 白發中）
        'z1': '東', 'z2': '南', 'z3': '西', 'z4': '北',
        'z5': '白', 'z6': '發', 'z7': '中'
    };

    static getImagePath(type, number) {
        let identifier;
        // リポジトリの画像は p_m1_1.gif, p_p1_1.gif, p_s1_1.gif, p_z1_1.gif の形式になっている
        // そのため type と number を組み合わせて直接ファイル名を作る
        identifier = `${this.typeMap[type]}${number}`;
        return `img/tiles/p_${identifier}_1.gif`;
    }

    static parse(text) {
        // [m123] のような形式の文字列を解析して画像に変換
        // 保守的な parse: テキスト中の [m123] 等の牌表記を検出し、牌群だけを取り出して1行のブロックにする。
        // 引数は文字列だが、この関数は主に init() から使われる。残存の replace ロジックは利用しない。
        // ※ ここでは元のテキストから牌表記を取り除いたテキスト行と、牌群を並べた行を生成して返す。
        const regex = /\[([mpsz])([1-9]+)\]/g;
        let match;
        let tilesHtml = '';
        // collect tiles
        while ((match = regex.exec(text)) !== null) {
            const type = match[1];
            const numbers = match[2];
            let groupHtml = '<span class="tile-group">';
            for (let num of numbers) {
                const key = type + num;
                const alt = this.tileMap[key] || '不明な牌';
                const imgPath = this.getImagePath(type, parseInt(num));
                groupHtml += `<img src="${imgPath}" alt="${alt}" class="mahjong-tile" title="${alt}">`;
            }
            groupHtml += '</span>';
            tilesHtml += groupHtml;
        }

        // remove all bracket tokens from text to leave pure textual content
        const textOnly = text.replace(/\[([mpsz])([1-9]+)\]/g, '').trim();
        if (tilesHtml === '') {
            // no tiles found, return original text
            return text;
        }
        // return combined HTML: text (if any) then a separate block for tiles
        const textPart = textOnly ? `<span class="quiz-text">${textOnly}</span>` : '';
        return textPart + `<div class="mahjong-tiles-row">${tilesHtml}</div>`;
    }

    static init() {
        // .mahjong-tiles クラスを持つ要素内の牌表記を変換
        document.querySelectorAll('.mahjong-tiles').forEach(elem => {
            elem.innerHTML = this.parse(elem.innerHTML);
        });
    }
}

// ページ読み込み完了時に初期化
document.addEventListener('DOMContentLoaded', () => {
    MahjongTileRenderer.init();
});