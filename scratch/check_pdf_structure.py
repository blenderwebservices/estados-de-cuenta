import pdfplumber

pdf_path = 'storage/app/private/01KTM10EH53828QP7XW7NVFSC4.pdf'
with pdfplumber.open(pdf_path) as pdf:
    for page_num, page in enumerate(pdf.pages):
        print(f"=== PAGE {page_num+1} ===")
        words = page.extract_words()
        
        lines_dict = {}
        for w in words:
            found = False
            for t in lines_dict:
                if abs(t - w['top']) <= 2.5:
                    lines_dict[t].append(w)
                    found = True
                    break
            if not found:
                lines_dict[w['top']] = [w]
                
        for top in sorted(lines_dict.keys()):
            row_words = sorted(lines_dict[top], key=lambda x: x['x0'])
            # We are interested in lines containing '$'
            if any(w['text'] == '$' for w in row_words):
                print(f"[{top:.1f}]", end=" ")
                for w in row_words:
                    print(f"'{w['text']}'({w['x0']:.1f}-{w['x1']:.1f})", end=" ")
                print()
