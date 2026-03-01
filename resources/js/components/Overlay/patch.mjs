import fs from 'fs';
let content = fs.readFileSync('ServerDetailOverlay.vue', 'utf8');
const pos = content.indexOf('<style scoped>');
if (pos !== -1) {
    fs.writeFileSync('ServerDetailOverlay.css', content.substring(pos));
    fs.writeFileSync('ServerDetailOverlay.vue', content.substring(0, pos) + '<style scoped src="./ServerDetailOverlay.css"></style>\n');
    console.log('Success');
} else {
    console.log('Not found');
}
