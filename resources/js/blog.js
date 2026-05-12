import hljs from "highlight.js/lib/core";
import javascript from "highlight.js/lib/languages/javascript";
import php from "highlight.js/lib/languages/php";
import bash from "highlight.js/lib/languages/bash";
import xml from "highlight.js/lib/languages/xml";
import css from "highlight.js/lib/languages/css";
import json from "highlight.js/lib/languages/json";
import sql from "highlight.js/lib/languages/sql";
import 'highlight.js/styles/github.css';
import ClipboardJS from "clipboard";

hljs.registerLanguage('javascript', javascript);
hljs.registerLanguage('php', php);
hljs.registerLanguage('bash', bash);
hljs.registerLanguage('xml', xml);
hljs.registerLanguage('html', xml);
hljs.registerLanguage('css', css);
hljs.registerLanguage('json', json);
hljs.registerLanguage('sql', sql);

document.addEventListener("DOMContentLoaded", function() {
    let codeBlocks = document.querySelectorAll("pre");
    codeBlocks.forEach((codeBlock) => {
        // add the plain text as an attribute to the code block
        codeBlock.setAttribute("data-clipboard-text", codeBlock.textContent);
        hljs.highlightElement(codeBlock.querySelector('code') || codeBlock);
    });

    addCopyToClipboardButton();
});

function addCopyToClipboardButton() {
    let codeBlocks = document.querySelectorAll("pre");
    codeBlocks.forEach((codeBlock) => {
        let copyButton = document.createElement("button");
        copyButton.innerHTML = "Copy";
        copyButton.classList.add("copy-button");

        let copy = new ClipboardJS(copyButton, {
            text: function(trigger) {
                return trigger.parentElement.getAttribute("data-clipboard-text");
            },
        });

        copy.on("success", function(e) {
            copyButton.innerHTML = "Copied!";
                setTimeout(() => {
                copyButton.innerHTML = "Copy";
            }, 1000);

            e.clearSelection();
        });

        codeBlock.appendChild(copyButton);
    });
}
