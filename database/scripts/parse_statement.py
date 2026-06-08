import sys
import os
import re
import json
import traceback
import pdfplumber

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

def parse_mx_date(date_str, default_year):
    months = {
        'ENE': 1, 'FEB': 2, 'MAR': 3, 'ABR': 4, 'MAY': 5, 'JUN': 6,
        'JUL': 7, 'AGO': 8, 'SEP': 9, 'OCT': 10, 'NOV': 11, 'DIC': 12,
        'AGOSTO': 8, 'SEPTIEMBRE': 9, 'OCTUBRE': 10, 'NOVIEMBRE': 11, 'DICIEMBRE': 12
    }
    date_str = date_str.upper().replace('DE', '').replace('.', '').strip()
    date_str = re.sub(r'\s+', ' ', date_str)
    
    m = re.match(r'^(\d{1,2})/(\d{1,2})/(\d{2,4})$', date_str)
    if m:
        day, month, year = int(m.group(1)), int(m.group(2)), int(m.group(3))
        if year < 100:
            year += 2000
        return f"{year:04d}-{month:02d}-{day:02d}"
        
    m = re.match(r'^(\d{1,2})/([A-Z]{3,})$', date_str)
    if m:
        day, mon_str = int(m.group(1)), m.group(2)
        month = months.get(mon_str, 1)
        return f"{default_year:04d}-{month:02d}-{day:02d}"
        
    m = re.match(r'^(\d{1,2})\s+([A-Z]{3,})$', date_str)
    if m:
        day, mon_str = int(m.group(1)), m.group(2)
        month = months.get(mon_str, 1)
        return f"{default_year:04d}-{month:02d}-{day:02d}"

    m = re.match(r'^(\d{1,2})-([A-Z]{3,})-(\d{2,4})$', date_str)
    if m:
        day, mon_str, year = int(m.group(1)), m.group(2), int(m.group(3))
        month = months.get(mon_str, 1)
        if year < 100:
            year += 2000
        return f"{year:04d}-{month:02d}-{day:02d}"

    return None

def detect_bank_type(pdf_path):
    with pdfplumber.open(pdf_path) as pdf:
        first_page_text = pdf.pages[0].extract_text() or ""
        text_upper = first_page_text.upper()
        
        if "BBVANET" in text_upper:
            return "BBVA CH"
                
        if "MAESTRA PYME" in text_upper:
            return "BBVA CH"
        elif "MAESTRA DOLARES PYME" in text_upper:
            return "BBVA US"
        elif "NO. DE TARJETA" in text_upper and "BBVA" in text_upper:
            return "BBVA TC"
        elif "BANAMEX" in text_upper or "CUENTA DE CHEQUES MONEDA NACIONAL" in text_upper or "BANCO NACIONAL DE MEXICO" in text_upper or "CITIBANAMEX" in text_upper:
            return "BANAMEX CH"
        elif "AMERICAN EXPRESS" in text_upper or "CORPORATE CARD" in text_upper:
            return "AMEX TC"
        elif "SCOTIABANK" in text_upper or "SCOTIA" in text_upper or "CU ASCENSO PM" in text_upper:
            return "SCOTIA CH"
            
    path_lower = pdf_path.lower()
    if "banamex" in path_lower:
        return "BANAMEX CH"
    elif "amex" in path_lower:
        return "AMEX TC"
    elif "bbva ch" in path_lower or "9601 ch" in path_lower:
        return "BBVA CH"
    elif "bbva tc" in path_lower or "6814 tc" in path_lower:
        return "BBVA TC"
    elif "bbva us" in path_lower or "6280 us" in path_lower:
        return "BBVA US"
    elif "scotia" in path_lower or "scotiabank" in path_lower:
        return "SCOTIA CH"
        
    return "UNKNOWN"

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
            print_month = int(m_print.group(2))
            print_year = int(m_print.group(3))
        else:
            print_month, print_year = 6, 2026
            
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
                    continue
                    
                amount, x_amt = nums[0]
                balance, _ = nums[1]
                
                # Distinguish Cargo vs Abono based on the first '$' X coordinate
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
                
    if transactions:
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

def parse_bbva_ch_us(pdf_path, is_us=False):
    # Check if the PDF is a BBVA Net web printout
    with pdfplumber.open(pdf_path) as pdf:
        text0 = pdf.pages[0].extract_text() or ""
        if "BBVANET" in text0.upper():
            return parse_bbva_net_web_print(pdf_path)

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
        
        m_period = re.search(r'Periodo\s+DEL\s+(\d{2}/\d{2}/\d{4})\s+AL\s+(\d{2}/\d{2}/\d{4})', text0)
        if m_period:
            metadata['period_start'] = parse_mx_date(m_period.group(1), 2026)
            metadata['period_end'] = parse_mx_date(m_period.group(2), 2026)
            default_year = int(m_period.group(2).split('/')[-1])
        else:
            default_year = 2026
            
        m_acc = re.search(r'No\.\s+de\s+Cuenta\s+(\d+)', text0)
        if m_acc:
            metadata['account'] = m_acc.group(1)
        m_clabe = re.search(r'No\.\s+Cuenta\s+CLABE\s+(\d+)', text0)
        if m_clabe:
            metadata['clabe'] = m_clabe.group(1)
            
        # Accents-flexible summary regexes (e.g. matching Liquidación or Liquidaci(cid:1)n)
        m_ini = re.search(r'Saldo\s+de\s+Liquidaci\S*n\s+Inicial\s+([\d,]+\.\d{2})', text0)
        if m_ini:
            metadata['saldo_inicial'] = clean_amount(m_ini.group(1))
        m_fin = re.search(r'Saldo\s+Final\s+\(\+\)\s+([\d,]+\.\d{2})', text0)
        if m_fin:
            metadata['saldo_final'] = clean_amount(m_fin.group(1))
            
        m_abonos = re.search(r'Dep\S*sitos\s+/\s+Abonos\s+\(\+\)\s+(\d+)\s+([\d,]+\.\d{2})', text0)
        if m_abonos:
            metadata['count_abonos'] = int(m_abonos.group(1))
            metadata['total_abonos'] = clean_amount(m_abonos.group(2))
            
        m_cargos = re.search(r'Retiros\s+/\s+Cargos\s+\(\-\)\s+(\d+)\s+([\d,]+\.\d{2})', text0)
        if m_cargos:
            metadata['count_cargos'] = int(m_cargos.group(1))
            metadata['total_cargos'] = clean_amount(m_cargos.group(2))
            
        current_tx = None
        
        for page_idx, page in enumerate(pdf.pages):
            words = [w for w in page.extract_words() if 135 <= w['top'] <= 735]
            
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
                row_text = ' '.join(w['text'] for w in row_words)
                
                m_date = re.match(r'^(\d{2}/[A-Z]{3})\s+(\d{2}/[A-Z]{3})', row_text)
                if m_date:
                    if current_tx:
                        transactions.append(current_tx)
                    
                    nums = []
                    for w in row_words:
                        val = w['text'].replace(',', '')
                        if re.match(r'^\d+\.\d{2}$', val):
                            cleaned_num = clean_amount(w['text'])
                            if cleaned_num is not None:
                                nums.append((cleaned_num, w['x0']))
                    
                    t_date = parse_mx_date(m_date.group(1), default_year)
                    code = row_words[2]['text'] if len(row_words) > 2 else ''
                    
                    amount = 0.0
                    is_abono = False
                    balance = 0.0
                    
                    if nums:
                        if len(nums) >= 3:
                            amount, x_amt = nums[0]
                            balance, _ = nums[1]
                        elif len(nums) == 2:
                            amount, x_amt = nums[0]
                            balance, _ = nums[1]
                        else:
                            amount, x_amt = nums[0]
                        
                        if 400 <= x_amt <= 465:
                            is_abono = True
                        else:
                            is_abono = False
                            
                    desc_parts = []
                    for w in row_words[3:]:
                        cleaned_word = clean_amount(w['text'])
                        if cleaned_word is not None and any(abs(cleaned_word - n[0]) < 0.01 for n in nums):
                            continue
                        desc_parts.append(w['text'])
                    
                    concept = ' '.join(desc_parts)
                    
                    current_tx = {
                        'fecha': t_date,
                        'codigo': code,
                        'etiqueta': concept,
                        'importe': amount if is_abono else -amount,
                        'saldo': balance if balance > 0 else None
                    }
                else:
                    if current_tx and row_text.strip():
                        if not any(k in row_text for k in ['PAGINA', 'Estado de Cuenta', 'MAESTRA', 'Av. Paseo de la Reforma', 'FECHA SALDO', 'No. Cuenta', 'No. Cliente']):
                            current_tx['etiqueta'] += ' ' + row_text.strip()
                            
        if current_tx:
            transactions.append(current_tx)
            
    for tx in transactions:
        tx['etiqueta'] = re.sub(r'\s+', ' ', tx['etiqueta']).strip()
        
    return metadata, transactions

def parse_bbva_tc(pdf_path):
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
        
        m_period = re.search(r'Periodo\s+.*Del\s+(\d{2}/\d{2}/\d{2})\s+al\s+(\d{2}/\d{2}/\d{2})', text0)
        if m_period:
            metadata['period_start'] = parse_mx_date(m_period.group(1), 2025)
            metadata['period_end'] = parse_mx_date(m_period.group(2), 2025)
            default_year = 2000 + int(m_period.group(2).split('/')[-1])
            period_end_date = metadata['period_end']
        else:
            default_year = 2025
            period_end_date = "2025-12-31"
            
        m_card = re.search(r'No\.\s+de\s+Tarjeta\s+([\d\s]+)', text0)
        if m_card:
            metadata['account'] = m_card.group(1).replace(' ', '')
        m_clabe = re.search(r'Cuenta\s+CLABE\s+(\d+)', text0)
        if m_clabe:
            metadata['clabe'] = m_clabe.group(1)
            
        m_ini = re.search(r'Saldo\s+Inicial\s+del\s+Periodo\s+\+\s+\$\s+(-?\s*[\d,]+\.\d{2})', text0)
        if m_ini:
            metadata['saldo_inicial'] = clean_amount(m_ini.group(1))
        m_fin = re.search(r'Saldo\s+al\s+Corte\s+\$\s+([\d,]+\.\d{2})', text0)
        if m_fin:
            metadata['saldo_final'] = clean_amount(m_fin.group(1))
            
        m_pagos = re.search(r'Pagos:\s+-\s+\$\s+([\d,]+\.\d{2})', text0)
        pagos = clean_amount(m_pagos.group(1)) if m_pagos else 0.0
        
        m_otros_abonos = re.search(r'Otros\s+Abonos\s+(?:\+\s+)?\$\s*(-?\s*[\d,]+\.\d{2})', text0)
        otros_abonos = clean_amount(m_otros_abonos.group(1)) if m_otros_abonos else 0.0
        
        metadata['total_abonos'] = pagos + otros_abonos
            
        m_compras = re.search(r'Compras\s+\+\s+\$\s+([\d,]+\.\d{2})', text0)
        m_dispos = re.search(r'Disposiciones\s+en\s+efectivo\s+\+\s+\$\s+([\d,]+\.\d{2})', text0)
        m_interest = re.search(r'Intereses\s+Ordinarios\s+\(sin\s+IVA\)\s+([\d,]+\.\d{2})', text0)
        m_iva = re.search(r'I\.V\.A\.\s+\+\s+\$\s+([\d,]+\.\d{2})', text0)
        
        cargos_sum = 0.0
        if m_compras: cargos_sum += clean_amount(m_compras.group(1))
        if m_dispos: cargos_sum += clean_amount(m_dispos.group(1))
        
        interest_value = clean_amount(m_interest.group(1)) if m_interest else 0.0
        iva_value = clean_amount(m_iva.group(1)) if m_iva else 0.0
        
        metadata['total_cargos'] = cargos_sum + interest_value + iva_value
        
        current_tx = None
        
        for page_idx, page in enumerate(pdf.pages):
            words = [w for w in page.extract_words() if 100 <= w['top'] <= 730]
            
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
                row_text = ' '.join(w['text'] for w in row_words)
                
                m_date = re.match(r'^(\d{2}/\d{2}/\d{2})\s+(\d{2}/\d{2}/\d{2})', row_text)
                if m_date:
                    if current_tx:
                        transactions.append(current_tx)
                    
                    amount_val = 0.0
                    is_abono = False
                    
                    for w in reversed(row_words):
                        cleaned_w = w['text'].replace('$', '').replace(',', '').strip()
                        if re.match(r'^-?\d+\.\d{2}$', cleaned_w):
                            amount_val = abs(float(cleaned_w))
                            if cleaned_w.startswith('-'):
                                is_abono = True
                            break
                            
                    t_date = parse_mx_date(m_date.group(1), default_year)
                    
                    desc_parts = []
                    for w in row_words[2:]:
                        cleaned_w = w['text'].replace('$', '').replace(',', '').strip()
                        if amount_val > 0 and abs(clean_amount(cleaned_w) or 0.0 - amount_val) < 0.01:
                            break
                        desc_parts.append(w['text'])
                    
                    concept = ' '.join(desc_parts)
                    
                    current_tx = {
                        'fecha': t_date,
                        'codigo': '',
                        'etiqueta': concept,
                        'importe': amount_val if is_abono else -amount_val,
                        'saldo': None
                    }
                else:
                    if current_tx and row_text.strip():
                        if not any(k in row_text for k in ['PAGINA', 'Estado de Cuenta', 'LINEA BBVA', 'Av. Paseo de la Reforma', 'TOTAL IMPORTES', 'FECHA APLICACION']):
                            current_tx['etiqueta'] += ' ' + row_text.strip()
                            
        if current_tx:
            transactions.append(current_tx)
            
    if interest_value > 0:
        transactions.append({
            'fecha': period_end_date,
            'codigo': '',
            'etiqueta': 'Intereses Ordinarios (sin IVA)',
            'importe': -interest_value,
            'saldo': None
        })
    if iva_value > 0:
        transactions.append({
            'fecha': period_end_date,
            'codigo': '',
            'etiqueta': 'I.V.A. (sobre intereses)',
            'importe': -iva_value,
            'saldo': None
        })
        
    cargos_count = 0
    abonos_count = 0
    for tx in transactions:
        tx['etiqueta'] = re.sub(r'\s+', ' ', tx['etiqueta']).strip()
        if tx['importe'] < 0:
            cargos_count += 1
        else:
            abonos_count += 1
            
    metadata['count_cargos'] = cargos_count
    metadata['count_abonos'] = abonos_count
    
    return metadata, transactions

def parse_amex_tc(pdf_path):
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
        text0 = ""
        for i in range(min(2, len(pdf.pages))):
            text0 += pdf.pages[i].extract_text() or ""
            
        m_period = re.search(r'Periodo\s+de\s+facturación:\s+Del\s*(\d{1,2})\s+de\s*([a-zA-ZáéíóúÁÉÍÓÚ]+)\s+al\s*(\d{1,2})\s+de\s*([a-zA-ZáéíóúÁÉÍÓÚ]+)\s+de\s*(\d{4})', text0)
        if m_period:
            year = int(m_period.group(5))
            metadata['period_start'] = parse_mx_date(f"{m_period.group(1)} {m_period.group(2)}", year)
            metadata['period_end'] = parse_mx_date(f"{m_period.group(3)} {m_period.group(4)}", year)
            default_year = year
        else:
            default_year = 2024
            
        m_acc = re.search(r'Número\s+de\s+Cuenta\s+([\d-]+)', text0)
        if m_acc:
            metadata['account'] = m_acc.group(1).replace('-', '')
            
        found_totals = False
        for page in pdf.pages[:2]:
            lines = page.extract_text().split('\n')
            for l in lines:
                m_totals = re.search(r'([\d,]+\.\d{2})\s+-\s+([\d,]+\.\d{2})\s+\+\s+([\d,]+\.\d{2})\s+=\s+([\d,]+\.\d{2})', l)
                if m_totals:
                    metadata['saldo_inicial'] = clean_amount(m_totals.group(1))
                    metadata['total_abonos'] = clean_amount(m_totals.group(2))
                    metadata['total_cargos'] = clean_amount(m_totals.group(3))
                    metadata['saldo_final'] = clean_amount(m_totals.group(4))
                    found_totals = True
                    break
            if found_totals:
                break
                
        if not found_totals:
            m_ini = re.search(r'Saldo\s+anterior\s+([\d,]+\.\d{2})', text0, re.IGNORECASE)
            if m_ini:
                metadata['saldo_inicial'] = clean_amount(m_ini.group(1))
            m_fin = re.search(r'Saldo\s+al\s+corte\s+([\d,]+\.\d{2})', text0, re.IGNORECASE)
            if m_fin:
                metadata['saldo_final'] = clean_amount(m_fin.group(1))
            m_abonos = re.search(r'Pagos\s+y\s+créditos\s+([\d,]+\.\d{2})', text0, re.IGNORECASE)
            if m_abonos:
                metadata['total_abonos'] = clean_amount(m_abonos.group(1))
            m_cargos = re.search(r'Nuevos\s+cargos\s+([\d,]+\.\d{2})', text0, re.IGNORECASE)
            if m_cargos:
                metadata['total_cargos'] = clean_amount(m_cargos.group(1))
            
        current_tx = None
        y_pagos = -1.0
        y_cargos = -1.0
        
        for page_idx, page in enumerate(pdf.pages):
            words = page.extract_words()
            
            for w in words:
                w_lower = w['text'].lower()
                if 'pagos' in w_lower and 'recibidos' in w_lower:
                    y_pagos = w['top']
                if 'nuevos' in w_lower and 'cargos' in w_lower:
                    y_cargos = w['top']
            
            words = [w for w in words if 100 <= w['top'] <= 730]
            
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
                row_text = ' '.join(w['text'] for w in row_words)
                
                if any(k in row_text for k in ['Total de nuevos cargos', 'Total de las transacciones', 'Resumen de Cuenta', 'Resumen de cuenta', 'Abreviación', 'Abreviaturas', 'Información al Tarjetahabiente']):
                    if current_tx:
                        transactions.append(current_tx)
                        current_tx = None
                    continue
                    
                m_date = re.match(r'^(\d{1,2})\s+de\s*([a-zA-Záéíóú]{3,})', row_text)
                if m_date:
                    if current_tx:
                        transactions.append(current_tx)
                    
                    t_date = parse_mx_date(f"{m_date.group(1)} {m_date.group(2)}", default_year)
                    
                    amount_val = 0.0
                    nums = []
                    for w in row_words:
                        val = w['text'].replace(',', '')
                        if re.match(r'^\d+\.\d{2}$', val):
                            cleaned_num = clean_amount(w['text'])
                            if cleaned_num is not None:
                                nums.append((cleaned_num, w['x0']))
                                
                    x_amt = 0.0
                    if nums:
                        nums_col = [n for n in nums if n[1] >= 500]
                        if nums_col:
                            amount_val, x_amt = nums_col[0]
                        else:
                            amount_val, x_amt = nums[-1]
                            
                    desc_parts = []
                    for w in row_words:
                        if w['text'] in [m_date.group(1), 'de', m_date.group(2)]:
                            continue
                        if amount_val > 0 and abs(clean_amount(w['text']) or 0.0 - amount_val) < 0.01 and w['x0'] >= 500:
                            continue
                        desc_parts.append(w['text'])
                        
                    concept = ' '.join(desc_parts)
                    
                    is_abono = False
                    if "PAGO RECIBIDO" in concept.upper() or "PAGO DE SU" in concept.upper() or "GRACIAS" in concept.upper():
                        is_abono = True
                    elif y_pagos > 0 and y_cargos > 0 and y_pagos < top < y_cargos:
                        is_abono = True
                    elif y_pagos > 0 and y_cargos < 0 and top > y_pagos:
                        is_abono = True
                    if re.search(r'\bCR\b', row_text):
                        is_abono = True
                        
                    current_tx = {
                        'fecha': t_date,
                        'codigo': '',
                        'etiqueta': concept,
                        'importe': amount_val if is_abono else -amount_val,
                        'saldo': None
                    }
                else:
                    if current_tx and row_text.strip():
                        if not any(k in row_text for k in ['PAGINA', 'Estado de Cuenta', 'American Express', 'Número de Cuenta', 'Fecha y detalle', 'Detalle de nuevos', 'Detalle de pagos']):
                            if re.search(r'\bCR\b', row_text):
                                current_tx['importe'] = abs(current_tx['importe'])
                            cleaned_row = re.sub(r'\s*\bCR\b\s*$', '', row_text).strip()
                            if cleaned_row:
                                current_tx['etiqueta'] += ' ' + cleaned_row
                                
        if current_tx:
            transactions.append(current_tx)
            
    cargos_count = 0
    abonos_count = 0
    for tx in transactions:
        tx['etiqueta'] = re.sub(r'\s+', ' ', tx['etiqueta']).strip()
        if tx['importe'] < 0:
            cargos_count += 1
        else:
            abonos_count += 1
            
    metadata['count_cargos'] = cargos_count
    metadata['count_abonos'] = abonos_count
    
    return metadata, transactions

def parse_banamex_ch(pdf_path):
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
        text0 = ""
        for i in range(min(2, len(pdf.pages))):
            text0 += pdf.pages[i].extract_text() or ""
            
        m_period = re.search(r'RESUMEN\s+DEL:\s+(\d{2}/[A-Z]{3}/\d{4})\s+AL\s+(\d{2}/[A-Z]{3}/\d{4})', text0)
        if m_period:
            metadata['period_start'] = parse_mx_date(m_period.group(1), 2025)
            metadata['period_end'] = parse_mx_date(m_period.group(2), 2025)
            default_year = int(m_period.group(2).split('/')[-1])
        else:
            default_year = 2025
            
        m_acc = re.search(r'Cheques\s+(\d{4}\s+\d{7})', text0)
        if m_acc:
            metadata['account'] = m_acc.group(1).replace(' ', '')
        else:
            m_acc_alt = re.search(r'CONTRATO\s+(\d{10})', text0)
            if m_acc_alt:
                metadata['account'] = m_acc_alt.group(1)
                
        m_clabe = re.search(r'CLABE\s+Interbancaria\s+(\d+)', text0)
        if m_clabe:
            metadata['clabe'] = m_clabe.group(1)
            
        m_ini = re.search(r'Saldo\s+Anterior\s+\$([\d,]+\.\d{2})', text0)
        if m_ini:
            metadata['saldo_inicial'] = clean_amount(m_ini.group(1))
        m_fin = re.search(r'SALDO\s+AL\s+\d+\s+DE\s+[A-Z]+\s+DE\s+\d{4}\s+\$([\d,]+\.\d{2})', text0)
        if m_fin:
            metadata['saldo_final'] = clean_amount(m_fin.group(1))
            
        m_abonos = re.search(r'\(\s*\+\s*\)\s*(\d+)\s+Dep\S*sitos\s+\$([\d,]+\.\d{2})', text0)
        if m_abonos:
            metadata['count_abonos'] = int(m_abonos.group(1))
            metadata['total_abonos'] = clean_amount(m_abonos.group(2))
        m_cargos = re.search(r'\(\s*\-\s*\)\s*(\d+)\s+Retiros\s+\$([\d,]+\.\d{2})', text0)
        if m_cargos:
            metadata['count_cargos'] = int(m_cargos.group(1))
            metadata['total_cargos'] = clean_amount(m_cargos.group(2))
            
        current_tx = None
        
        for page_idx, page in enumerate(pdf.pages):
            words = [w for w in page.extract_words() if 100 <= w['top'] <= 750]
            
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
                row_text = ' '.join(w['text'] for w in row_words)
                
                m_date = re.match(r'^(\d{2})\s+([A-Z]{3})\b', row_text)
                if m_date:
                    if current_tx:
                        transactions.append(current_tx)
                    
                    t_date = parse_mx_date(f"{m_date.group(1)} {m_date.group(2)}", default_year)
                    concept = ' '.join(w['text'] for w in row_words[2:])
                    
                    current_tx = {
                        'fecha': t_date,
                        'codigo': '',
                        'etiqueta': concept,
                        'importe': 0.0,
                        'saldo': None
                    }
                else:
                    if current_tx and row_text.strip():
                        if not any(k in row_text for k in ['PAGINA', 'ESTADO DE CUENTA', 'DETALLE DE OPERACIONES', 'FECHA CONCEPTO', 'SALDO ANTERIOR']):
                            if re.search(r'\bHORA\s+\d{2}:\d{2}\s+SUC\s+\d+', row_text):
                                nums = []
                                for w in row_words:
                                    val = w['text'].replace(',', '')
                                    if re.match(r'^\d+\.\d{2}$', val):
                                        cleaned_num = clean_amount(w['text'])
                                        if cleaned_num is not None:
                                            nums.append((cleaned_num, w['x0']))
                                            
                                amount = 0.0
                                is_abono = False
                                balance = 0.0
                                
                                if nums:
                                    if len(nums) >= 2:
                                        amount, x_amt = nums[0]
                                        balance, _ = nums[1]
                                    else:
                                        amount, x_amt = nums[0]
                                    
                                    if 320 <= x_amt <= 410:
                                        is_abono = True
                                    else:
                                        is_abono = False
                                        
                                current_tx['importe'] = amount if is_abono else -amount
                                if balance > 0:
                                    current_tx['saldo'] = balance
                            else:
                                current_tx['etiqueta'] += ' ' + row_text.strip()
                                
        if current_tx:
            transactions.append(current_tx)
            
    for tx in transactions:
        tx['etiqueta'] = re.sub(r'\s+', ' ', tx['etiqueta']).strip()
        tx['etiqueta'] = re.sub(r'HORA\s+\d{2}:\d{2}\s+SUC\s+\d+\s*$', '', tx['etiqueta']).strip()
        tx['etiqueta'] = re.sub(r'CAJA\s+\d+\s+AUT\s+\d+\s*$', '', tx['etiqueta']).strip()
        
    return metadata, transactions

def parse_scotia_ch(pdf_path):
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
        
        m_period = re.search(r'Periodo\s*(\d{2}-[A-Z]{3}-\d{2})/(\d{2}-[A-Z]{3}-\d{2})', text0)
        if m_period:
            metadata['period_start'] = parse_mx_date(m_period.group(1), 2025)
            metadata['period_end'] = parse_mx_date(m_period.group(2), 2025)
            default_year = int(metadata['period_end'].split('-')[0])
        else:
            default_year = 2025
            
        m_acc = re.search(r'Cuenta\s+(\d+)', text0)
        if m_acc:
            metadata['account'] = m_acc.group(1)
        m_clabe = re.search(r'CLABE\s+(\d+)', text0)
        if m_clabe:
            metadata['clabe'] = m_clabe.group(1)
            
        m_ini = re.search(r'S\s*aldo\s+inicial\s*=\s*\$([\d,]+\.\d{2})', text0, re.IGNORECASE)
        if not m_ini:
            m_ini = re.search(r'S\s*aldo\s+inicial\s*\$([\d,]+\.\d{2})', text0, re.IGNORECASE)
        if m_ini:
            metadata['saldo_inicial'] = clean_amount(m_ini.group(1))
            
        m_fin = re.search(r'S\s*aldo\s+final\s*=\s*\$([\d,]+\.\d{2})', text0, re.IGNORECASE)
        if not m_fin:
            m_fin = re.search(r'S\s*aldo\s+final\s*\$([\d,]+\.\d{2})', text0, re.IGNORECASE)
        if not m_fin:
            m_fin = re.search(r'S\s*aldo\s+final\s+del\s+a\s+cuenta\s*\$([\d,]+\.\d{2})', text0, re.IGNORECASE)
        if not m_fin:
            m_fin = re.search(r'S\s*aldo\s+final\s+de\s+la\s+cuenta\s*\$([\d,]+\.\d{2})', text0, re.IGNORECASE)
        if m_fin:
            metadata['saldo_final'] = clean_amount(m_fin.group(1))
            
        m_abonos = re.search(r'Depósitos\s*\$([\d,]+\.\d{2})', text0)
        if not m_abonos:
            m_abonos = re.search(r'Dep\S*sitos\s*\$([\d,]+\.\d{2})', text0)
        if m_abonos:
            metadata['total_abonos'] = clean_amount(m_abonos.group(1))
            
        m_cargos = re.search(r'Retiros\s*\$([\d,]+\.\d{2})', text0)
        retiros = clean_amount(m_cargos.group(1)) if m_cargos else 0.0
        
        m_comisiones = re.search(r'Comisiones\s*cobradas\s*\$([\d,]+\.\d{2})', text0, re.IGNORECASE)
        if not m_comisiones:
            m_comisiones = re.search(r'Comisiones\s*\*?\s*\$([\d,]+\.\d{2})', text0, re.IGNORECASE)
        comisiones = clean_amount(m_comisiones.group(1)) if m_comisiones else 0.0
        
        m_impuestos = re.search(r'Impuestos\s*\$([\d,]+\.\d{2})', text0, re.IGNORECASE)
        if not m_impuestos:
            m_impuestos = re.search(r'Impuestos\s*\*?\s*\$([\d,]+\.\d{2})', text0, re.IGNORECASE)
        impuestos = clean_amount(m_impuestos.group(1)) if m_impuestos else 0.0
        
        metadata['total_cargos'] = retiros + comisiones + impuestos
            
        current_tx = None
        
        for page_idx, page in enumerate(pdf.pages):
            words = [w for w in page.extract_words() if 80 <= w['top'] <= 750]
            
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
                row_text = ' '.join(w['text'] for w in row_words)
                
                m_date = re.match(r'^(\d{2})\s+([A-Z]{3})\b', row_text)
                if m_date and any(w['text'].startswith('$') for w in row_words):
                    if current_tx:
                        transactions.append(current_tx)
                        
                    t_date = parse_mx_date(f"{m_date.group(1)} {m_date.group(2)}", default_year)
                    
                    nums = []
                    for i, w in enumerate(row_words):
                        if w['text'].startswith('$'):
                            cleaned = clean_amount(w['text'])
                            if cleaned is not None:
                                nums.append((cleaned, w['x0']))
                                
                    amount = 0.0
                    is_abono = False
                    balance = 0.0
                    
                    if nums:
                        if len(nums) >= 2:
                            amount, x_amt = nums[0]
                            balance, _ = nums[1]
                        else:
                            amount, x_amt = nums[0]
                            
                        if x_amt < 450:
                            is_abono = True
                        else:
                            is_abono = False
                            
                    desc_parts = []
                    for w in row_words[2:]:
                        if w['text'].startswith('$'):
                            continue
                        desc_parts.append(w['text'])
                        
                    concept = ' '.join(desc_parts)
                    
                    current_tx = {
                        'fecha': t_date,
                        'codigo': '',
                        'etiqueta': concept,
                        'importe': amount if is_abono else -amount,
                        'saldo': balance if balance > 0 else None
                    }
                else:
                    if current_tx and row_text.strip():
                        if not any(k in row_text for k in ['PAGINA', 'Cuenta', 'Detalledetusmovimientos', 'Fecha Concepto', 'Saldo inicial', 'Saldo final', 'ResumendeSaldos', 'LAS TASAS DE']):
                            current_tx['etiqueta'] += ' ' + row_text.strip()
                            
        if current_tx:
            transactions.append(current_tx)
            
    cargos_count = 0
    abonos_count = 0
    for tx in transactions:
        tx['etiqueta'] = re.sub(r'\s+', ' ', tx['etiqueta']).strip()
        if tx['importe'] < 0:
            cargos_count += 1
        else:
            abonos_count += 1
            
    metadata['count_cargos'] = cargos_count
    metadata['count_abonos'] = abonos_count
    
    return metadata, transactions

def main():
    if len(sys.argv) < 2:
        print(json.dumps({'success': False, 'error': 'Missing PDF path argument'}))
        sys.exit(1)
        
    pdf_path = sys.argv[1]
    bank_type = sys.argv[2] if len(sys.argv) > 2 else None
    
    if not os.path.exists(pdf_path):
        print(json.dumps({'success': False, 'error': f'File not found: {pdf_path}'}))
        sys.exit(1)
        
    try:
        if not bank_type or bank_type == "UNKNOWN":
            bank_type = detect_bank_type(pdf_path)
            
        if bank_type == "BBVA CH":
            metadata, transactions = parse_bbva_ch_us(pdf_path, is_us=False)
        elif bank_type == "BBVA US":
            metadata, transactions = parse_bbva_ch_us(pdf_path, is_us=True)
        elif bank_type == "BBVA TC":
            metadata, transactions = parse_bbva_tc(pdf_path)
        elif bank_type == "AMEX TC":
            metadata, transactions = parse_amex_tc(pdf_path)
        elif bank_type == "BANAMEX CH":
            metadata, transactions = parse_banamex_ch(pdf_path)
        elif bank_type == "SCOTIA CH":
            metadata, transactions = parse_scotia_ch(pdf_path)
        else:
            print(json.dumps({'success': False, 'error': f'Unsupported bank type: {bank_type}'}))
            sys.exit(1)
            
        metadata['bank'] = bank_type
        
        output = {
            'success': True,
            'metadata': metadata,
            'transactions': transactions
        }
        print(json.dumps(output, indent=2, ensure_ascii=False))
        
    except Exception as e:
        output = {
            'success': False,
            'error': str(e),
            'traceback': traceback.format_exc()
        }
        print(json.dumps(output, indent=2, ensure_ascii=False))
        sys.exit(1)

if __name__ == '__main__':
    main()
