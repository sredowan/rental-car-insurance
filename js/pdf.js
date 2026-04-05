/**
 * Rental Shield - Dynamic PDF Generator
 * Dependencies: jsPDF, jsPDF-AutoTable
 */

window.generatePolicyPDF = async function(policyData, userData) {
    if (!window.jspdf) {
        console.error("jsPDF is not loaded!");
        return;
    }

    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({
        orientation: 'p',
        unit: 'mm',
        format: 'a4'
    });

    const primaryColor = [29, 78, 216]; // #1D4ED8 (Primary Blue)
    const navyColor = [11, 30, 61];     // #0B1E3D (Navy)
    const emeraldColor = [16, 185, 129]; // #10B981 (Emerald Green)

    // Helper to format date
    const formatDate = (dateStr) => {
        if (!dateStr) return '—';
        return new Date(dateStr).toLocaleDateString('en-AU', { day: 'numeric', month: 'short', year: 'numeric' });
    };

    // Load logo as Data URL
    let logoDataUrl = null;
    try {
        const response = await fetch('assets/images/logo.png');
        const blob = await response.blob();
        logoDataUrl = await new Promise((resolve) => {
            const reader = new FileReader();
            reader.onloadend = () => resolve(reader.result);
            reader.readAsDataURL(blob);
        });
    } catch (e) {
        console.warn("Could not load logo for PDF formatting.", e);
    }

    // --- 1. Header & Branding ---
    if (logoDataUrl) {
        // Adjust width/height as appropriate for your logo proportions
        doc.addImage(logoDataUrl, 'PNG', 14, 15, 60, 18);
    }

    doc.setFont("helvetica", "bold");
    doc.setFontSize(22);
    doc.setTextColor(navyColor[0], navyColor[1], navyColor[2]);
    doc.text("Certificate of Insurance", 200, 25, { align: "right" });

    doc.setFontSize(10);
    doc.setFont("helvetica", "normal");
    doc.setTextColor(100, 100, 100);
    doc.text("Rental Shield", 200, 32, { align: "right" });
    doc.text("ABN: 19 686 732 043", 200, 36, { align: "right" });
    doc.text("Email: info@rentalshield.com.au", 200, 40, { align: "right" });
    doc.text("Web: www.rentalshield.com.au", 200, 44, { align: "right" });

    let y = 60;

    // --- 2. Policy Details Section ---
    doc.setFont("helvetica", "bold");
    doc.setFontSize(14);
    doc.setTextColor(navyColor[0], navyColor[1], navyColor[2]);
    doc.text("Policy Summary", 14, y);
    y += 6;

    // Draw a box around the details
    doc.setDrawColor(230, 230, 230);
    doc.setFillColor(248, 250, 252); // Very light blue/gray background
    doc.roundedRect(14, y, 182, 50, 3, 3, 'FD');

    // Inside the box
    doc.setFontSize(10);
    doc.setTextColor(60, 60, 60);

    const pdY1 = y + 10;
    const pdY2 = y + 22;
    const pdY3 = y + 34;
    const pdY4 = y + 46;

    const col1 = 20;
    const col2 = 80;
    const col3 = 140;

    doc.setFont("helvetica", "bold");
    doc.text("Policy Number:", col1, pdY1);
    doc.setFont("helvetica", "normal");
    doc.setTextColor(0, 0, 0);
    doc.text(policyData.policy_number || "—", col1 + 30, pdY1);

    doc.setTextColor(60, 60, 60);
    doc.setFont("helvetica", "bold");
    doc.text("Main Driver:", col2, pdY1);
    doc.setFont("helvetica", "normal");
    doc.setTextColor(0, 0, 0);
    doc.text(userData.full_name || userData.name || "—", col2 + 25, pdY1);

    doc.setTextColor(60, 60, 60);
    doc.setFont("helvetica", "bold");
    doc.text("Total Paid:", col3, pdY1);
    doc.setFont("helvetica", "bold");
    doc.setTextColor(emeraldColor[0], emeraldColor[1], emeraldColor[2]);
    doc.text(`$${parseFloat(policyData.total_price || 0).toFixed(2)}`, col3 + 20, pdY1);

    doc.setTextColor(60, 60, 60);
    doc.setFont("helvetica", "bold");
    doc.text("Start Date:", col1, pdY2);
    doc.setFont("helvetica", "normal");
    doc.setTextColor(0, 0, 0);
    doc.text(formatDate(policyData.start_date), col1 + 22, pdY2);

    doc.setTextColor(60, 60, 60);
    doc.setFont("helvetica", "bold");
    doc.text("End Date:", col2, pdY2);
    doc.setFont("helvetica", "normal");
    doc.setTextColor(0, 0, 0);
    doc.text(formatDate(policyData.end_date), col2 + 18, pdY2);

    doc.setTextColor(60, 60, 60);
    doc.setFont("helvetica", "bold");
    doc.text("Duration:", col3, pdY2);
    doc.setFont("helvetica", "normal");
    doc.setTextColor(0, 0, 0);
    doc.text(`${policyData.duration_days || 0} days`, col3 + 18, pdY2);

    doc.setTextColor(60, 60, 60);
    doc.setFont("helvetica", "bold");
    doc.text("Coverage Limit:", col1, pdY3);
    doc.setFont("helvetica", "normal");
    doc.setTextColor(0, 0, 0);
    doc.text(`$${parseInt(policyData.coverage_amount || 0).toLocaleString()}`, col1 + 30, pdY3);

    doc.setTextColor(60, 60, 60);
    doc.setFont("helvetica", "bold");
    doc.text("State:", col2, pdY3);
    doc.setFont("helvetica", "normal");
    doc.setTextColor(0, 0, 0);
    doc.text(policyData.state || "—", col2 + 12, pdY3);

    doc.setTextColor(60, 60, 60);
    doc.setFont("helvetica", "bold");
    doc.text("Excess Amount:", col3, pdY3);
    doc.setFont("helvetica", "bold");
    doc.setTextColor(emeraldColor[0], emeraldColor[1], emeraldColor[2]);
    doc.text("$0", col3 + 30, pdY3);

    doc.setTextColor(60, 60, 60);
    doc.setFont("helvetica", "bold");
    doc.text("Status:", col1, pdY4);
    doc.setFont("helvetica", "bold");
    doc.setTextColor(emeraldColor[0], emeraldColor[1], emeraldColor[2]);
    doc.text("ACTIVE", col1 + 15, pdY4);

    y += 58;

    // --- 3. Important Notice at Counter ---
    doc.setFillColor(254, 243, 199); // Amber background
    doc.roundedRect(14, y, 182, 22, 2, 2, 'F');
    doc.setTextColor(146, 64, 14); // Dark Amber text
    doc.setFont("helvetica", "bold");
    doc.text("At the Rental Counter:", 20, y + 8);
    doc.setFont("helvetica", "normal");
    // Breaking text manually or via splitTextToSize
    const counterTip = "When staff offer CDW or LDW waivers, politely decline. You are already fully covered by Rental Shield up to your coverage limit. Saying yes would mean double-paying for the same protection.";
    const splitTip = doc.splitTextToSize(counterTip, 170);
    doc.text(splitTip, 20, y + 14);

    y += 30;

    // --- 4. What is Covered & Not Covered Tables ---
    // Using AutoTable for structured list
    const inclusions = [
        ["Collision and accidental damage", "Included"],
        ["Theft of the rental vehicle", "Included"],
        ["Windscreen and auto glass repair/replacement", "Included"],
        ["Tyre, undercarriage, and roof damage", "Included"],
        ["Administrative, loss-of-use, and towing fees charged by rental company", "Included"]
    ];

    const exclusions = [
        ["Driving off-road or on unsealed surfaces (unless expressly authorized by rental company)."],
        ["Driving under the influence of alcohol, drugs, or illegal substances."],
        ["Damage to third-party vehicles or property (This is covered by the rental company's mandatory CTP/Third Party insurance by law)."],
        ["Any incidents occurring while in breach of your rental agreement terms."]
    ];

    doc.autoTable({
        startY: y,
        head: [['What is Covered', 'Status']],
        body: inclusions,
        theme: 'grid',
        headStyles: { fillColor: primaryColor, textColor: 255 },
        columnStyles: { 
            0: { cellWidth: 140 },
            1: { cellWidth: 40, textColor: emeraldColor, fontStyle: 'bold' } 
        },
        margin: { left: 14 }
    });

    y = doc.lastAutoTable.finalY + 10;

    doc.autoTable({
        startY: y,
        head: [['What is NOT Covered']],
        body: exclusions,
        theme: 'grid',
        headStyles: { fillColor: [220, 38, 38], textColor: 255 }, // Red
        margin: { left: 14 }
    });

    y = doc.lastAutoTable.finalY + 14;

    // --- 5. Claims Process ---
    doc.setFontSize(14);
    doc.setFont("helvetica", "bold");
    doc.setTextColor(navyColor[0], navyColor[1], navyColor[2]);
    doc.text("In the event of an incident or claim", 14, y);
    y += 8;

    doc.setFontSize(10);
    doc.setFont("helvetica", "normal");
    doc.setTextColor(60, 60, 60);

    const claimSteps = [
        "1. Ensure everyone is safe.",
        "2. Photograph all damage immediately and thoroughly.",
        "3. Follow the rental company's instructions. You may need to pay the excess amount they demand directly to them first.",
        "4. Obtain and keep all final invoices, the repair matrix, and the incident report from the rental desk.",
        "5. Log into your Rental Shield dashboard (www.rentalshield.com.au) and lodge your claim under \"My Claims\".",
        "6. We will review your uploaded documents and reimburse your bank account within 3 to 5 business days."
    ];

    claimSteps.forEach(step => {
        const splitStep = doc.splitTextToSize(step, 180);
        doc.text(splitStep, 14, y);
        y += (5 * splitStep.length);
    });

    // Save PDF
    const fileName = `Rental_Shield_Policy_${policyData.policy_number || 'Document'}.pdf`;
    doc.save(fileName);
};
