import pdfplumber
import re
import json
import os

def clean_amount(val):
    if not val:
        return 0.0
    val = val.replace('$', '').replace(' ', '').replace(',', '').strip()
    if not val:
        return 0.0
    try:
        return float(val)
    except ValueError:
        return None

def parse_bbva_net_web_print(pdf_path):
    metadata = {
        'account': '',
        'clabe': '',
        'period_start': '',
        'period_end': '',
        'saldo_inicial': 0.0,
        'saldo_final': 0.0,
        'total_cargos': 0.0,
        'total_abonos': 0.0,
        'count_cargos': 0,
        'count_abonos': 0
    }
    transactions = []
    
    with pdfplumber.open(pdf_path) as pdf:
        text0 = pdf.pages[0].extract_text() or ""
        
        # Extract print date / year
        m_print = re.search(r'(\d{2})/(\d{2})/(\d{4})', text0)
        if m_print:
            print_day = int(m_print.group(1))
            print_month = int(m_print.group(2))
            print_year = int(m_print.group(3))
        else:
            print_day, print_month, print_year = 8, 4, 2026
            
        m_acc = re.search(r'Número\s+de\s+cuenta:\s*(\d+)', text0)
        if m_acc:
            metadata['account'] = m_acc.group(1)
            
        # Parse pages
        for page_idx, page in enumerate(pdf.pages):
            words = page.extract_words()
            # Transactions are in the middle of the page
            min_y = 200 if page_idx == 0 else 25
            max_y = 755
            table_words = [w for w in words if min_y <= w['top'] <= max_y]
            
            # Group words by top coordinate
            lines_dict = {}
            for w in table_words:
                found = False
                for t in lines_dict:
                    if abs(t - w['top']) <= 2.5:
                        lines_dict[t].append(w)
                        found = True
                        break
                if not found:
                    lines_dict[w['top']] = [w]
            
            # Sort tops
            sorted_tops = sorted(lines_dict.keys())
            if not sorted_tops:
                continue
                
            # Group lines into blocks representing a transaction
            blocks = []
            current_block = [sorted_tops[0]]
            for t in sorted_tops[1:]:
                if t - current_block[-1] > 6.0:
                    blocks.append(current_block)
                    current_block = [t]
                else:
                    current_block.append(t)
            if current_block:
                blocks.append(current_block)
                
            # Parse each block
            for block in blocks:
                # Find the main row (contains date DD-MM and '$')
                main_top = None
                main_words = []
                for top in block:
                    row_words = sorted(lines_dict[top], key=lambda x: x['x0'])
                    row_text = ' '.join(w['text'] for w in row_words)
                    
                    # Look for date like '06-04' and '$'
                    m_date = re.match(r'^\d{2}-\d{2}\b', row_text)
                    if m_date and any(w['text'] == '$' for w in row_words):
                        main_top = top
                        main_words = row_words
                        break
                
                if main_top is None:
                    continue
                    
                # Parse main line
                row_text = ' '.join(w['text'] for w in main_words)
                m_date = re.match(r'^(\d{2})-(\d{2})', row_text)
                day, month = int(m_date.group(1)), int(m_date.group(2))
                
                # Resolve year
                if month > print_month and print_month == 1:
                    year = print_year - 1
                else:
                    year = print_year
                t_date = f"{year:04d}-{month:02d}-{day:02d}"
                
                # Extract numbers
                nums = []
                for w in main_words:
                    val = w['text'].replace(',', '')
                    if re.match(r'^\d+\.\d{2}$', val):
                        cleaned_num = clean_amount(w['text'])
                        if cleaned_num is not None:
                            nums.append((cleaned_num, w['x0']))
                            
                if len(nums) < 2:
                    # Invalid transaction line
                    continue
                    
                amount, x_amt = nums[0]
                balance, _ = nums[1]
                
                # Distinguish Cargo vs Abono based on the first '$' X coordinate
                # In main_words, let's find the X0 of the first '$'
                first_dollar_x = 0.0
                for w in main_words:
                    if w['text'] == '$':
                        first_dollar_x = w['x0']
                        break
                        
                is_abono = first_dollar_x > 370
                
                # Extract description from other lines in this block
                desc_lines = []
                for top in block:
                    if top == main_top:
                        continue
                    row_words = sorted(lines_dict[top], key=lambda x: x['x0'])
                    row_text = ' '.join(w['text'] for w in row_words).strip()
                    if row_text:
                        desc_lines.append(row_text)
                concept = ' '.join(desc_lines)
                
                transactions.append({
                    'fecha': t_date,
                    'codigo': '',
                    'etiqueta': concept,
                    'importe': amount if is_abono else -amount,
                    'saldo': balance
                })
                
    # Sort transactions chronologically (from oldest to newest) to match metadata logic
    # Wait, the list in the PDF is descending. Let's keep it descending for now.
    if transactions:
        # Calculate summary metrics
        cargos = [tx['importe'] for tx in transactions if tx['importe'] < 0]
        abonos = [tx['importe'] for tx in transactions if tx['importe'] > 0]
        
        metadata['total_cargos'] = sum(abs(c) for c in cargos)
        metadata['total_abonos'] = sum(abonos)
        metadata['count_cargos'] = len(cargos)
        metadata['count_abonos'] = len(abonos)
        
        # Newest transaction is at index 0
        metadata['saldo_final'] = transactions[0]['saldo']
        
        # Oldest transaction is at index -1
        last_tx = transactions[-1]
        if last_tx['importe'] < 0:
            metadata['saldo_inicial'] = last_tx['saldo'] + abs(last_tx['importe'])
        else:
            metadata['saldo_inicial'] = last_tx['saldo'] - last_tx['importe']
            
        metadata['period_start'] = min(tx['fecha'] for tx in transactions)
        metadata['period_end'] = max(tx['fecha'] for tx in transactions)
        
    return metadata, transactions

# Test all 5 uploaded BBVA CH files
files = [
  'storage/app/private/01KTM10EH53828QP7XW7NVFSC4.pdf',
  'storage/app/private/01KTM12GMCSKJCC7D28NBS74HK.pdf',
  'storage/app/private/01KTM13ECRNFH4QRTB4EKR3RBY.pdf',
  'storage/app/private/01KTM18MGVC4FPTG1KP1GH7A4H.pdf',
  'storage/app/private/01KTM1A6H6N8B789W8JPMJDQBV.pdf'
]

for f in files:
    print(f"=== Testing {f} ===")
    meta, txs = parse_bbva_net_web_print(f)
    print("Metadata:", json.dumps(meta, indent=2))
    print(f"Transactions count: {len(txs)}")
    # Verify math
    calc_final = meta['saldo_inicial'] + meta['total_abonos'] - meta['total_cargos']
    diff = abs(calc_final - meta['saldo_final'])
    print(f"Math validation: Initial ({meta['saldo_inicial']:.2f}) + Abonos ({meta['total_abonos']:.2f}) - Cargos ({meta['total_cargos']:.2f}) = {calc_final:.2f} (Expected: {meta['saldo_final']:.2f}, Diff: {diff:.4f})")
    print("-" * 50)
