import openpyxl
import csv
import os

def convert_all_sheets_to_csv(xlsx_path, output_dir):
    wb = openpyxl.load_workbook(xlsx_path, data_only=True)
    if not os.path.exists(output_dir):
        os.makedirs(output_dir)
    
    for sheet_name in wb.sheetnames:
        sh = wb[sheet_name]
        safe_sheet_name = "".join([c if c.isalnum() else "_" for c in sheet_name])
        csv_path = os.path.join(output_dir, f"Ribo_Testcases_{safe_sheet_name}.csv")
        with open(csv_path, 'w', newline='', encoding='utf-8') as f:
            writer = csv.writer(f)
            for r in sh.rows:
                writer.writerow([cell.value for cell in r])
        print(f"Converted sheet '{sheet_name}' to {csv_path}")

if __name__ == "__main__":
    xlsx_path = 'c:/Users/Rovick/Downloads/Final_Production/docs/Ribo Testcases.xlsx'
    output_dir = 'c:/Users/Rovick/Downloads/Final_Production/docs/testcase_exports'
    convert_all_sheets_to_csv(xlsx_path, output_dir)
