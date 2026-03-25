import pandas as pd

try:
    xl = pd.ExcelFile(r'c:\Users\Rovick\Downloads\Final_Production\docs\Ribo Testcases.xlsx')
    for sheet in xl.sheet_names:
        print(f"Checking sheet: {sheet}")
        df = xl.parse(sheet)
        mask = df.apply(lambda row: row.astype(str).str.contains('Conversation|Omnichannel|Facebook|Messenger|WhatsApp|Telegram|Messaging', case=False).any(), axis=1)
        matches = df[mask]
        if not matches.empty:
            print(f"Matches found in {sheet}:")
            print(matches.iloc[:5, :5]) # Print first few matches
except Exception as e:
    print(f"Error: {e}")
