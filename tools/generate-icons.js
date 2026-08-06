const sharp = require("sharp");
const fs = require("fs");
const path = require("path");

const src = path.join(__dirname, "..", "public", "icons", "icon-512.png");
const outDir = path.join(__dirname, "..", "public", "icons");
const sizes = [72, 96, 128, 144, 152, 384];

if (!fs.existsSync(src)) {
    console.error("Source icon not found:", src);
    process.exit(1);
}

(async () => {
    try {
        await Promise.all(
            sizes.map(async (s) => {
                const out = path.join(outDir, `icon-${s}.png`);
                await sharp(src).resize(s, s).toFile(out);
                console.log("Created", out);
            }),
        );
        console.log("All icons generated.");
    } catch (err) {
        console.error("Error generating icons:", err);
        process.exit(1);
    }
})();
