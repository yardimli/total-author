import { Document, Packer, Paragraph, TextRun } from "docx";
import { writeFile } from "node:fs/promises";
const doc = new Document({
    sections: [
        {
            children: [
                new Paragraph({ text: "The Last Ferry", heading: "Heading1" }),
                new Paragraph({
                    children: [
                        new TextRun("Mara Vale arrived at "),
                        new TextRun({ text: "Bellwater", italics: true }),
                        new TextRun(" before dawn."),
                    ],
                }),
                new Paragraph(
                    "The lighthouse keeper carried a brass compass in his coat.",
                ),
            ],
        },
    ],
});
await writeFile(
    new URL("./fixtures/story.docx", import.meta.url),
    await Packer.toBuffer(doc),
);
