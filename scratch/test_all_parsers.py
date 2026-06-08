import os
import subprocess
import json

examples_dir = 'assets/ejemplos'
folders = os.listdir(examples_dir)

results = {}

for f in sorted(folders):
    f_path = os.path.join(examples_dir, f)
    if os.path.isdir(f_path) and not f.startswith('.'):
        pdfs = [x for x in os.listdir(f_path) if x.endswith('.pdf')]
        for pdf in sorted(pdfs):
            pdf_sample = os.path.join(f_path, pdf)
            print(f"Testing PDF {pdf_sample}...")
            
            cmd = ['python3', 'database/scripts/parse_statement.py', pdf_sample]
            res = subprocess.run(cmd, capture_output=True, text=True)
            
            if res.returncode == 0:
                try:
                    data = json.loads(res.stdout)
                    if data.get('success'):
                        meta = data['metadata']
                        txs = data['transactions']
                        detected_bank = meta['bank']
                        
                        # Validate Math
                        sum_cargos = sum(t['importe'] for t in txs if t['importe'] < 0)
                        sum_abonos = sum(t['importe'] for t in txs if t['importe'] > 0)
                        
                        diff_cargos = abs(sum_cargos) - meta['total_cargos']
                        diff_abonos = abs(sum_abonos) - meta['total_abonos']
                        
                        # Check bank class for math balance
                        # TC = Credit Card (AMEX TC, BBVA TC)
                        # CH/US = checking accounts
                        is_credit_card = 'TC' in detected_bank
                        
                        if is_credit_card:
                            # Debt balance formula: Saldo Final = Saldo Inicial - sum(cargos) - sum(abonos)
                            # Note: sum_cargos is negative, sum_abonos is positive.
                            # So: Saldo Final = Saldo Inicial + abs(sum_cargos) - sum_abonos
                            calc_final_balance = meta['saldo_inicial'] - sum_cargos - sum_abonos
                        else:
                            # Checking account formula: Saldo Final = Saldo Inicial + sum_abonos + sum_cargos
                            calc_final_balance = meta['saldo_inicial'] + sum_abonos + sum_cargos
                            
                        diff_balance = calc_final_balance - meta['saldo_final']
                        
                        print(f"  Detected Bank: {detected_bank}")
                        print(f"  Account: {meta['account']}, CLABE: {meta['clabe']}")
                        print(f"  Period: {meta['period_start']} to {meta['period_end']}")
                        print(f"  Transactions count: {len(txs)} (Cargos: {meta['count_cargos']}, Abonos: {meta['count_abonos']})")
                        print(f"  Initial Balance: {meta['saldo_inicial']:.2f}, Final Balance: {meta['saldo_final']:.2f}")
                        print(f"  Total Cargos: {meta['total_cargos']:.2f} (Calculated: {abs(sum_cargos):.2f}, Diff: {diff_cargos:.2f})")
                        print(f"  Total Abonos: {meta['total_abonos']:.2f} (Calculated: {sum_abonos:.2f}, Diff: {diff_abonos:.2f})")
                        print(f"  Calculated Final Balance: {calc_final_balance:.2f} (Diff: {diff_balance:.2f})")
                        
                        results[pdf_sample] = {
                            'bank_type': detected_bank,
                            'status': 'SUCCESS',
                            'tx_count': len(txs),
                            'diff_cargos': round(diff_cargos, 2),
                            'diff_abonos': round(diff_abonos, 2),
                            'diff_balance': round(diff_balance, 2)
                        }
                    else:
                        print(f"  Result: PARSER ERROR - {data.get('error')}")
                        results[pdf_sample] = {'status': 'PARSER ERROR', 'error': data.get('error')}
                except Exception as e:
                    print(f"  Result: JSON DECODE/MATH ERROR - {str(e)}")
                    print("  STDOUT:", res.stdout[:1000])
                    results[pdf_sample] = {'status': 'MATH ERROR', 'error': str(e)}
            else:
                print(f"  Result: CMD FAIL (Code {res.returncode})")
                print("  STDERR:", res.stderr)
                results[pdf_sample] = {'status': 'CMD FAIL', 'error': res.stderr}
            print("-" * 50)

print("\n=== SUMMARY OF ALL SAMPLES ===")
success_count = sum(1 for r in results.values() if r.get('status') == 'SUCCESS')
balanced_count = sum(1 for r in results.values() if r.get('status') == 'SUCCESS' and abs(r['diff_balance']) < 0.1)
print(f"Total PDFs parsed: {len(results)}")
print(f"Successful parse: {success_count}")
print(f"Perfect math balance (cuadre): {balanced_count}")
