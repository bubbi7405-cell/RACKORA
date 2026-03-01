const fs = require('fs');
let content = fs.readFileSync('ServerDetailOverlay.vue', 'utf8');
content = content.replace(/<style scoped>[\s\S]*?<\/style>/, '<style scoped src="./server-overlay-styles.css"></style>');
fs.writeFileSync('ServerDetailOverlay.vue', content);
