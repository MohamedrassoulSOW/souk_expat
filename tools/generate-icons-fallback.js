const fs = require("fs");
const path = require("path");

const src = path.join(__dirname, "..", "public", "icons", "icon-512.png");
const outDir = path.join(__dirname, "..", "public", "icons");
const sizes = [384, 152, 144, 128, 96, 72];

if (!fs.existsSync(src)) {
    console.error("Source icon not found:", src);
    process.exit(1);
}

try {
    sizes.forEach((s) => {
        const out = path.join(outDir, `icon-${s}.png`);
        fs.copyFileSync(src, out);
        console.log("Copied placeholder to", out);
    });
    console.log("Fallback icons generated (placeholders).");
} catch (err) {
    console.error("Error creating placeholder icons:", err);
    process.exit(1);
}
