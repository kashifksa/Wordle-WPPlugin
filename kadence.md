# Kadence Theme Design Guide — Wordle Hint Pro Network

## Objective
Design WordPress pages that visually match the premium plugin aesthetic (glassmorphic cards, gold accents, Inter font).

---

## 1. Global Typography
**Path:** Kadence → Customizer → Typography

| Setting | Value |
|---|---|
| Body Font | `Inter` (Google Fonts) |
| Body Size | 16px |
| Body Weight | 400 |
| Heading Font | `Inter` |
| Heading Weight | 800 / 900 |
| H1 Size | 2.8rem |
| H2 Size | 2rem |
| H3 Size | 1.4rem |
| Line Height (Body) | 1.6 |
| Line Height (Headings) | 1.2 |

---

## 2. Color Settings
**Path:** Kadence → Customizer → Colors

| Setting | Value |
|---|---|
| Accent / Link Color | `#c9b458` (Wordle Gold) |
| Heading Color | `#111111` |
| Body Text Color | `#333333` |
| Background | `#ffffff` |
| Button Color | `#6aaa64` (Wordle Green) |
| Button Hover Color | `#c9b458` |

---

## 3. Page Layout Settings (Per Page)

Set in the **Kadence Settings sidebar** on each page editor.

### Content/Guide Pages (articles, how-to, strategy)
- **Content Width:** `Narrow` (780px) — editorial, premium feel
- **Sidebar:** None
- **Header:** Sticky

### Archive / Data Pages
- **Content Width:** `Wide` or `Full Width` (1100px)
- **Sidebar:** None

---

## 4. Best Kadence Block Layouts Per Page Type

### 📄 Strategy / Guide Pages (e.g., Starting Words, Strategy Guide)
```
Layout: Single Column, Max-Width 780px
Blocks to use:
├── Kadence "Advanced Text"     → Intro paragraphs
├── Kadence "Icon List"         → Tips / bullet points (use check ✓ icons)
├── Kadence "Info Box"          → Callout tips (gold bg: #c9b458)
└── Kadence "Table of Contents" → Long guides (critical for SEO)
```

### 📊 Data / Archive Pages (e.g., Letter Frequency, Puzzle Index)
```
Layout: Full Width, Max-Width 1100px
Blocks to use:
├── Kadence "Row Layout"     → 3-column stat boxes at top
├── Kadence "Advanced Table" → Letter frequency / puzzle data
└── Gutenberg Table block    → Simple indexed lists
```

### ❓ FAQ Pages (e.g., Is Wordle the Same for Everyone?, How to Play)
```
Layout: Single Column, Max-Width 780px
Blocks to use:
├── Kadence "Accordion" → One accordion item per FAQ question
│   (Google loves this for FAQ rich results / schema)
└── Kadence "Advanced Text" → Intro paragraph above accordion
```

---

## 5. The "Premium Article" Block Structure

Use this repeating pattern for every guide/article page:

```
[H1] Main title — bold, large
[Paragraph] Short intro (2-3 sentences)
──────────────────────────────────────
[Kadence Row — 3 columns] Key stats / facts at the top
──────────────────────────────────────
[H2] Section heading
[Paragraph] Body text
[Kadence Icon List] Bullet points with check icons
──────────────────────────────────────
[Kadence Info Box] Gold callout tip / warning
──────────────────────────────────────
[H2] Next section
... repeat ...
──────────────────────────────────────
[CTA Block] Internal link → "View Today's Wordle Hints →"
```

---

## 6. Block Styling Standards

| Property | Value |
|---|---|
| Section padding (top/bottom) | 60px |
| Inner card padding | 30px |
| Border radius (Info Boxes) | 16px |
| Internal link color | `#c9b458` (not default blue) |
| Button style | Rounded, green `#6aaa64` → gold hover |

---

## 7. Key Kadence Tips

- **Use `Kadence Row Layout`** instead of default Gutenberg Columns — better spacing control and responsive settings.
- **Enable "Sticky Header"** — greatly improves UX on long-form content pages.
- **Add `border-radius: 16px`** to Info Boxes to match plugin card aesthetic.
- **Set block padding consistently** — 60px top/bottom for sections, 30px for inner elements.
- For **internal links**, use gold color `#c9b458` to match the plugin's accent.
- **Table of Contents block** is non-negotiable on any article over 800 words — it boosts dwell time and SEO signals.

---

## 8. Page-by-Page Application

| Page | Layout | Width | Key Blocks |
|---|---|---|---|
| `/wordle-starting-words/` | Article | 780px | Advanced Text, Icon List, Info Box, ToC |
| `/wordle-strategy-guide/` | Article | 780px | Advanced Text, Icon List, Accordion, ToC |
| `/how-to-play-wordle/` | Article | 780px | Advanced Text, Icon List, Info Box |
| `/wordle-letter-frequency/` | Data | 1100px | Row Layout (stats), Advanced Table |
| `/wordle-hard-mode-tips/` | Article | 780px | Advanced Text, Icon List, Info Box |
| `/wordle-vs-quordle/` | Comparison | 1100px | Row Layout, Advanced Table |
| `/wordle-answers-[month]-[year]/` | Archive | 1100px | `[wordle_monthly_roundup]` shortcode |
