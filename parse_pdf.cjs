const fs = require('fs');
const pdf = require('pdf-parse');

let dataBuffer = fs.readFileSync('docs/Social Media connection - RIBO.pdf');

pdf(dataBuffer).then(function (data) {
    fs.writeFileSync('docs/social_media_parsed.txt', data.text);
    console.log("Successfully parsed to social_media_parsed.txt");
});
