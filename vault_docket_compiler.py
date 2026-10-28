import sys
import os
import json
import fitz  # PyMuPDF
from PIL import Image

def build_cover_page(applicant_meta, docs_meta, target_bank="Partner Bank"):
    """
    Builds a luxury A4 Executive BFS Cover Sheet using PyMuPDF vector drawing
    """
    doc = fitz.open()
    page = doc.new_page(width=595.3, height=841.9)  # Standard A4 in points (72 dpi)

    # Colors
    c_black = (0.05, 0.05, 0.07)
    c_dark_teal = (0.04, 0.35, 0.35)
    c_accent_green = (0.08, 0.65, 0.45)
    c_gray_bg = (0.97, 0.98, 0.99)
    c_border = (0.85, 0.88, 0.91)
    c_text_dark = (0.1, 0.12, 0.15)
    c_text_muted = (0.4, 0.45, 0.5)

    # 1. Header Banner Box
    page.draw_rect(fitz.Rect(35, 35, 560, 115), color=None, fill=c_black)
    
    # BFS Title
    page.insert_text(fitz.Point(55, 70), "BFS LOAN DOSSIER", fontsize=20, fontname="helv", color=(1, 1, 1))
    page.insert_text(fitz.Point(55, 92), "BHARAT FINANCIAL SERVICES • EXECUTIVE SUBMISSION DOCKET", fontsize=9.5, fontname="helv", color=(0.7, 0.8, 0.9))

    # Reference Badge (Right aligned in banner)
    ref_id = applicant_meta.get("loan_id", "REF-PENDING")
    page.draw_rect(fitz.Rect(400, 52, 540, 85), color=None, fill=(0.15, 0.18, 0.22))
    page.insert_text(fitz.Point(412, 66), "APPLICATION REF", fontsize=7.5, color=(0.6, 0.7, 0.8))
    page.insert_text(fitz.Point(412, 79), ref_id, fontsize=11, color=(1, 1, 1))

    # 2. Key Applicant Information Grid (Card Box)
    page.draw_rect(fitz.Rect(35, 130, 560, 240), color=c_border, fill=c_gray_bg, width=1)
    
    # Grid Header
    page.insert_text(fitz.Point(50, 150), "BORROWER & FACILITY DETAILS", fontsize=11, fontname="helv", color=c_dark_teal)
    page.draw_line(fitz.Point(50, 158), fitz.Point(545, 158), color=c_border, width=0.8)

    # Column 1
    cust_name = applicant_meta.get("customer_name", "N/A")
    mobile = applicant_meta.get("mobile", "N/A")
    loan_type = applicant_meta.get("loan_type", "General Loan")
    amt_req = applicant_meta.get("loan_amount_requested", 0)
    try:
        amt_req_fmt = f"INR {float(amt_req):,.2f}"
    except:
        amt_req_fmt = str(amt_req)

    rm_name = applicant_meta.get("assigned_to", "Central Operations")
    co_app = applicant_meta.get("co_applicant", "None")

    y_start = 180
    # Left Column
    page.insert_text(fitz.Point(50, y_start), "Primary Applicant:", fontsize=9, color=c_text_muted)
    page.insert_text(fitz.Point(145, y_start), cust_name, fontsize=10, fontname="helv", color=c_text_dark)

    page.insert_text(fitz.Point(50, y_start + 20), "Contact Mobile:", fontsize=9, color=c_text_muted)
    page.insert_text(fitz.Point(145, y_start + 20), mobile, fontsize=9.5, color=c_text_dark)

    page.insert_text(fitz.Point(50, y_start + 40), "Co-Applicant:", fontsize=9, color=c_text_muted)
    page.insert_text(fitz.Point(145, y_start + 40), co_app, fontsize=9.5, color=c_text_dark)

    # Right Column
    page.insert_text(fitz.Point(310, y_start), "Target Institution:", fontsize=9, color=c_text_muted)
    page.insert_text(fitz.Point(405, y_start), target_bank, fontsize=10, fontname="helv", color=c_accent_green)

    page.insert_text(fitz.Point(310, y_start + 20), "Loan Facility:", fontsize=9, color=c_text_muted)
    page.insert_text(fitz.Point(405, y_start + 20), f"{loan_type}", fontsize=9.5, color=c_text_dark)

    page.insert_text(fitz.Point(310, y_start + 40), "Amount Requested:", fontsize=9, color=c_text_muted)
    page.insert_text(fitz.Point(405, y_start + 40), amt_req_fmt, fontsize=10.5, fontname="helv", color=c_dark_teal)

    # 3. Document Index / Table of Contents
    page.draw_rect(fitz.Rect(35, 255, 560, 770), color=c_border, fill=(1, 1, 1), width=1)
    page.insert_text(fitz.Point(50, 278), "INDEX OF SUBMITTED LOAN DOCUMENTS", fontsize=11, fontname="helv", color=c_dark_teal)
    
    # Table headers
    page.draw_rect(fitz.Rect(50, 290, 545, 310), color=None, fill=c_gray_bg)
    page.insert_text(fitz.Point(58, 304), "#", fontsize=8.5, fontname="helv", color=c_text_muted)
    page.insert_text(fitz.Point(85, 304), "Document Name & Details", fontsize=8.5, fontname="helv", color=c_text_muted)
    page.insert_text(fitz.Point(320, 304), "Category", fontsize=8.5, fontname="helv", color=c_text_muted)
    page.insert_text(fitz.Point(440, 304), "Status", fontsize=8.5, fontname="helv", color=c_text_muted)
    page.insert_text(fitz.Point(510, 304), "Pages", fontsize=8.5, fontname="helv", color=c_text_muted)

    curr_y = 328
    for idx, d in enumerate(docs_meta[:20], 1):  # Limit to 20 for single cover sheet
        name = d.get("name", "Document")
        if len(name) > 36:
            name = name[:34] + ".."
        cat = d.get("category", "General")
        status = d.get("status", "Verified")
        p_count = str(d.get("page_count", 1))

        page.insert_text(fitz.Point(58, curr_y), str(idx), fontsize=8.5, color=c_text_muted)
        page.insert_text(fitz.Point(85, curr_y), name, fontsize=9, fontname="helv", color=c_text_dark)
        page.insert_text(fitz.Point(320, curr_y), cat[:20], fontsize=8.5, color=c_text_muted)
        
        status_color = c_accent_green if "verif" in status.lower() or "appr" in status.lower() else c_text_muted
        page.insert_text(fitz.Point(440, curr_y), status, fontsize=8.5, color=status_color)
        page.insert_text(fitz.Point(515, curr_y), p_count, fontsize=8.5, color=c_text_dark)

        page.draw_line(fitz.Point(50, curr_y + 6), fitz.Point(545, curr_y + 6), color=(0.93, 0.94, 0.96), width=0.5)
        curr_y += 21

    # 4. Footer Note
    page.draw_line(fitz.Point(35, 785), fitz.Point(560, 785), color=c_border, width=0.5)
    page.insert_text(fitz.Point(40, 805), "CONFIDENTIAL BFS BANKING DOCKET • SUBMITTED FOR OFFICIAL EVALUATION • ALL RIGHTS RESERVED", fontsize=7.5, color=c_text_muted)
    page.insert_text(fitz.Point(40, 818), f"Compiled by RM: {rm_name} • Generated on BFS 10x On-Premise Vault Engine", fontsize=7, color=(0.6, 0.65, 0.7))

    return doc

def add_watermark(page, watermark_text):
    """
    Applies an anti-fraud security watermark diagonally across the page
    """
    rect = page.rect
    # Center coordinates
    p = fitz.Point(rect.width * 0.15, rect.height * 0.70)
    
    # Watermark text with low alpha (semi-transparent grey) at 45 degree angle
    try:
        page.insert_text(
            p,
            watermark_text,
            fontsize=20,
            fontname="helv",
            color=(0.75, 0.75, 0.80),
            morph=(p, fitz.Matrix(45)),
            fill_opacity=0.35
        )
    except Exception as e:
        # Fallback to header stamp if morph fails
        page.insert_text(
            fitz.Point(30, 20),
            watermark_text,
            fontsize=9,
            fontname="helv",
            color=(0.6, 0.6, 0.65)
        )

def compile_docket(config_json_path):
    """
    Reads config JSON and produces combined master PDF
    """
    with open(config_json_path, "r", encoding="utf-8") as f:
        cfg = json.load(f)

    applicant = cfg.get("applicant", {})
    documents = cfg.get("documents", [])
    target_bank = cfg.get("target_bank", "Partner Financial Institution")
    apply_wm = cfg.get("apply_watermark", True)
    output_path = cfg.get("output_path", "BFS_Bank_Docket.pdf")

    watermark_msg = f"BFS LOAN SUBMISSION FOR {target_bank.upper()} • REF: {applicant.get('loan_id', 'LOAN')}"

    # Master PDF
    master = fitz.open()

    # Pre-analyze documents & count pages
    docs_meta = []
    temp_docs = []

    for d in documents:
        fpath = d.get("file_path", "")
        if not os.path.exists(fpath):
            continue

        ext = os.path.splitext(fpath)[1].lower()
        if ext == ".pdf":
            try:
                sub_pdf = fitz.open(fpath)
                p_cnt = len(sub_pdf)
                docs_meta.append({
                    "name": d.get("document_name", os.path.basename(fpath)),
                    "category": d.get("document_category", "General"),
                    "status": d.get("qa_status", d.get("status", "Verified")),
                    "page_count": p_cnt,
                    "doc_obj": sub_pdf
                })
            except Exception as e:
                pass
        elif ext in [".jpg", ".jpeg", ".png", ".bmp", ".webp"]:
            try:
                # Convert image to single-page PDF
                img_doc = fitz.open()
                img = Image.open(fpath)
                w, h = img.size
                
                # Fit onto A4
                a4_w, a4_h = 595.3, 841.9
                page = img_doc.new_page(width=a4_w, height=a4_h)
                
                # Scale image keeping aspect ratio with 25pt margins
                max_w = a4_w - 50
                max_h = a4_h - 60
                scale = min(max_w / w, max_h / h, 1.0)
                disp_w = w * scale
                disp_h = h * scale
                x = (a4_w - disp_w) / 2
                y = (a4_h - disp_h) / 2
                
                # Insert image into page
                page.insert_image(fitz.Rect(x, y, x + disp_w, y + disp_h), filename=fpath)
                
                docs_meta.append({
                    "name": d.get("document_name", os.path.basename(fpath)),
                    "category": d.get("document_category", "General"),
                    "status": d.get("qa_status", d.get("status", "Verified")),
                    "page_count": 1,
                    "doc_obj": img_doc
                })
            except Exception as e:
                pass

    # 1. Generate BFS Executive Cover Page
    cover_pdf = build_cover_page(applicant, docs_meta, target_bank)
    master.insert_pdf(cover_pdf)

    # 2. Append each document and watermark if enabled
    for d in docs_meta:
        sub_doc = d["doc_obj"]
        start_page = len(master)
        master.insert_pdf(sub_doc)
        end_page = len(master)

        if apply_wm:
            for p_idx in range(start_page, end_page):
                add_watermark(master[p_idx], watermark_msg)

    # 3. Save Master Docket
    total_pages = len(master)
    os.makedirs(os.path.dirname(output_path), exist_ok=True)
    master.save(output_path, garbage=4, deflate=True)
    master.close()

    result = {
        "success": True,
        "output_path": output_path,
        "total_pages": total_pages,
        "docs_compiled": len(docs_meta),
        "file_size": os.path.getsize(output_path)
    }
    print(json.dumps(result))

if __name__ == "__main__":
    if len(sys.argv) < 2:
        print(json.dumps({"success": False, "error": "Missing config JSON argument"}))
        sys.exit(1)
    
    cfg_file = sys.argv[1]
    compile_docket(cfg_file)
