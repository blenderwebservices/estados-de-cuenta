import os
import re
import json
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
    
    m = re.match(r'^(\d{2})/(\d{2})/(\d{2,4})$', date_str)
    if m:
        day, month, year = int(m.group(1)), int(m.group(2)), int(m.group(3))
        if year < 100:
            year += 2000
        return f"{year:04d}-{month:02d}-{day:02d}"
        
    m = re.match(r'^(\d{2})/([A-Z]{3,})$', date_str)
    if m:
        day, mon_str = int(m.group(1)), m.group(2)
        month = months.get(mon_str, 1)
        return f"{default_year:04d}-{month:02d}-{day:02d}"
        
    m = re.match(r'^(\d{1,2})\s+([A-Z]{3,})$', date_str)
    if m:
        day, mon_str = int(m.group(1)), m.group(2)
        month = months.get(mon_str, 1)
        return f"{default_year:04d}-{month:02d}-{day:02d}"

    return None

def parse_bbva_ch(pdf_path):
    metadata = {
        'bank': 'BBVA CH',
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
        text0 = pdf.pages[0].extract_text()
        
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
            
        m_ini = re.search(r'Saldo\s+de\s+Liquidación\s+Inicial\s+([\d,]+\.\d{2})', text0)
        if m_ini:
            metadata['saldo_inicial'] = clean_amount(m_ini.group(1))
        m_fin = re.search(r'Saldo\s+Final\s+\(\+\)\s+([\d,]+\.\d{2})', text0)
        if m_fin:
            metadata['saldo_final'] = clean_amount(m_fin.group(1))
        m_abonos = re.search(r'Depósitos\s+/\s+Abonos\s+\(\+\)\s+(\d+)\s+([\d,]+\.\d{2})', text0)
        if m_abonos:
            metadata['count_abonos'] = int(m_abonos.group(1))
            metadata['total_abonos'] = clean_amount(m_abonos.group(2))
        m_cargos = re.search(r'Retiros\s+/\s+Cargos\s+\(\-\)\s+(\d+)\s+([\d,]+\.\d{2})', text0)
        if m_cargos:
            metadata['count_cargos'] = int(m_cargos.group(1))
            metadata['total_cargos'] = clean_amount(m_cargos.group(2))
            
        current_tx = None
        
        for page_idx, page in enumerate(pdf.pages):
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
                    
                    code = ''
                    if len(row_words) > 2:
                        code = row_words[2]['text']
                    
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
                        
                        if 400 <= x_amt <= 460:
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
                        'saldo': balance
                    }
                else:
                    if current_tx and row_text.strip():
                        if not any(k in row_text for k in ['PAGINA', 'Estado de Cuenta', 'MAESTRA PYME', 'Av. Paseo de la Reforma', 'FECHA SALDO', 'No. Cuenta', 'No. Cliente']):
                            current_tx['etiqueta'] += ' ' + row_text.strip()
                            
        if current_tx:
            transactions.append(current_tx)
            
    metadata['transactions'] = transactions
    return metadata

if __name__ == '__main__':
    res = parse_bbva_ch('assets/ejemplos/BBVA CH/01. 9601 CH BBVA.pdf')
    print('Metadata:', json.dumps({k: v for k, v in res.items() if k != 'transactions'}, indent=2))
    print('Total transactions found:', len(res['transactions']))
    print('First 5 transactions:')
    for t in res['transactions'][:5]:
        print('  ', t)
