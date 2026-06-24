from __future__ import annotations

import math
import textwrap
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
OUTPUT = ROOT / "Guide_installation_Kamoro_HestiaPredict.pdf"
GIT_URL = "https://github.com/MaheryNyToky/Kamoro-reservation-facturation.git"


def clean(text: str) -> str:
    replacements = {
        "\u2019": "'",
        "\u2018": "'",
        "\u201c": '"',
        "\u201d": '"',
        "\u2013": "-",
        "\u2014": "-",
        "\u2026": "...",
        "\u00a0": " ",
        "\u2192": "->",
        "\u20ac": "EUR",
    }
    for src, dst in replacements.items():
        text = text.replace(src, dst)
    return text


def pdf_escape(text: str) -> str:
    text = clean(text)
    return (
        text.replace("\\", "\\\\")
        .replace("(", "\\(")
        .replace(")", "\\)")
        .encode("cp1252", "replace")
        .decode("cp1252")
    )


class PdfWriter:
    def __init__(self) -> None:
        self.objects: list[bytes] = []
        self.pages: list[int] = []
        self.add_object("<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>")
        self.add_object("<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>")

    def add_object(self, body: str | bytes) -> int:
        if isinstance(body, str):
            data = body.encode("latin-1", "replace")
        else:
            data = body
        self.objects.append(data)
        return len(self.objects)

    def add_page(self, lines: list[tuple[str, int, str]]) -> None:
        content = ["BT"]
        for index, (text, size, style) in enumerate(lines):
            font = "/F2" if style == "bold" else "/F1"
            x = 54
            y = text_positions[index]
            content.append(f"{font} {size} Tf")
            content.append(f"1 0 0 1 {x} {y} Tm")
            content.append(f"({pdf_escape(text)}) Tj")
        content.append("ET")
        stream = "\n".join(content).encode("latin-1", "replace")
        stream_obj = self.add_object(
            b"<< /Length "
            + str(len(stream)).encode("ascii")
            + b" >>\nstream\n"
            + stream
            + b"\nendstream"
        )
        page_obj = self.add_object(
            f"<< /Type /Page /Parent 0 0 R /MediaBox [0 0 612 792] "
            f"/Resources << /Font << /F1 1 0 R /F2 2 0 R >> >> "
            f"/Contents {stream_obj} 0 R >>"
        )
        self.pages.append(page_obj)

    def write(self, path: Path) -> None:
        pages_kids = " ".join(f"{page} 0 R" for page in self.pages)
        pages_obj = self.add_object(f"<< /Type /Pages /Kids [{pages_kids}] /Count {len(self.pages)} >>")
        for page_num in self.pages:
            page_body = self.objects[page_num - 1].decode("latin-1")
            page_body = page_body.replace("/Parent 0 0 R", f"/Parent {pages_obj} 0 R")
            self.objects[page_num - 1] = page_body.encode("latin-1")
        catalog_obj = self.add_object(f"<< /Type /Catalog /Pages {pages_obj} 0 R >>")

        output = bytearray(b"%PDF-1.4\n%\xe2\xe3\xcf\xd3\n")
        offsets = [0]
        for index, obj in enumerate(self.objects, start=1):
            offsets.append(len(output))
            output.extend(f"{index} 0 obj\n".encode("ascii"))
            output.extend(obj)
            output.extend(b"\nendobj\n")
        xref_pos = len(output)
        output.extend(f"xref\n0 {len(self.objects) + 1}\n".encode("ascii"))
        output.extend(b"0000000000 65535 f \n")
        for offset in offsets[1:]:
            output.extend(f"{offset:010d} 00000 n \n".encode("ascii"))
        output.extend(
            f"trailer\n<< /Size {len(self.objects) + 1} /Root {catalog_obj} 0 R >>\n"
            f"startxref\n{xref_pos}\n%%EOF\n".encode("ascii")
        )
        path.write_bytes(output)


def wrap_paragraph(text: str, width: int = 88) -> list[str]:
    text = clean(text).strip()
    if not text:
        return [""]
    return textwrap.wrap(text, width=width, break_long_words=False, replace_whitespace=False)


def build_lines() -> list[tuple[str, int, str]]:
    items: list[tuple[str, int, str]] = []

    def title(text: str) -> None:
        items.append((text, 18, "bold"))
        items.append(("", 10, "regular"))

    def h1(text: str) -> None:
        items.append(("", 10, "regular"))
        items.append((text, 14, "bold"))

    def h2(text: str) -> None:
        items.append(("", 8, "regular"))
        items.append((text, 12, "bold"))

    def p(text: str) -> None:
        for line in wrap_paragraph(text):
            items.append((line, 10, "regular"))

    def bullet(text: str) -> None:
        wrapped = wrap_paragraph(text, 84)
        if wrapped:
            items.append((f"- {wrapped[0]}", 10, "regular"))
            for line in wrapped[1:]:
                items.append((f"  {line}", 10, "regular"))

    def cmd(text: str) -> None:
        for line in wrap_paragraph(text, 82):
            items.append((f"    {line}", 9, "regular"))

    title("Guide d'installation - Kamoro Reservation Facturation")
    p("Ce guide correspond au lancement local actuel du projet. Il utilise le script ./dev.sh, qui demarre l'IA FastAPI, le backend Laravel et le frontend Flutter Web.")
    p(f"Lien du projet GitHub : {GIT_URL}")

    h1("1. Ce qu'il faut sur l'ordinateur")
    bullet("Git")
    bullet("PHP 8.4 ou superieur")
    bullet("Composer 2.x")
    bullet("Python 3.11 ou superieur")
    bullet("Flutter")
    bullet("Google Chrome")
    bullet("SQLite est deja gere par le projet via database.sqlite")
    p("Le script dev.sh automatise ensuite le reste : il cree le venv Python de l'IA si besoin et installe les dependances Composer manquantes.")

    h1("2. Recuperer le projet")
    bullet("Ouvre un terminal dans le dossier ou tu veux placer le projet.")
    bullet("Telecharge le depot :")
    cmd(f"git clone {GIT_URL}")
    bullet("Entre dans le dossier du projet :")
    cmd("cd Kamoro-reservation-facturation")

    h1("3. Installer les dependances principales")
    p("Si PHP, Composer, Python ou Flutter ne sont pas encore disponibles, installe-les avant de lancer le projet.")
    h2("Verification rapide")
    cmd("php -v")
    cmd("composer --version")
    cmd("python3 --version")
    cmd("flutter --version")
    p("Si une commande n'est pas reconnue, il faut d'abord installer l'outil correspondant.")

    h1("4. Lancer l'application")
    p("Depuis la racine du projet, lance la commande suivante :")
    cmd("./dev.sh")
    bullet("Le script arrete les anciens services s'ils existent deja.")
    bullet("Il cree automatiquement hestia-ai/.venv si l'environnement Python n'existe pas.")
    bullet("Il installe automatiquement les dependances Composer si le dossier vendor manque.")
    bullet("Il construit Flutter Web et le sert sur le port 8080.")
    p("Le premier lancement peut prendre du temps, surtout si les dependances doivent etre telechargees.")

    h1("5. Ouvrir l'application")
    bullet("Quand le script affiche Environnement pret, ouvre Chrome.")
    bullet("Va sur :")
    cmd("http://127.0.0.1:8080/index.html")
    bullet("Dashboard Laravel : http://127.0.0.1:8000/dashboard")
    bullet("Swagger IA : http://127.0.0.1:8001/docs")

    h1("6. Comptes de demonstration")
    bullet("Administrateur : admin@kamorohotel.com / admin123")
    bullet("Reception : reco1@kamorohotel.com / reco123")

    h1("7. Donnees deja presentes")
    p("La base locale contient deja des chambres et des reservations de test. Tu peux ouvrir les filtres Tout, En attente, Non payees et Payees pour verifier le comportement.")
    bullet("Un sejour du 22 au 23 doit apparaitre le 22 et le 23.")
    bullet("Un client paye doit rester visible dans Payees, meme si le check-in est encore en attente.")

    h1("8. Arreter l'application")
    bullet("Ferme les fenetres de l'application si besoin.")
    bullet("Relance ensuite ./dev.sh pour arretee les anciens services et repartir proprement.")

    h1("9. Probleme frequent : une commande n'est pas reconnue")
    bullet("Installe l'outil manquant.")
    bullet("Ferme puis rouvre le terminal.")
    bullet("Retape la commande de verification correspondante.")

    h1("10. Probleme frequent : le premier lancement est lent")
    bullet("C'est normal si Docker n'est pas utilise.")
    bullet("Le script doit parfois telecharger des paquets Python ou Composer.")
    bullet("Laisse la machine terminer avant de relancer.")

    h1("11. Probleme frequent : le port est deja utilise")
    bullet("Ferme une ancienne instance de dev.sh.")
    bullet("Si besoin, ferme les processus restes ouverts puis relance ./dev.sh.")

    h1("12. A retenir")
    bullet("Le point d'entree principal est ./dev.sh.")
    bullet("Aucune modification manuelle du backend ou de l'IA n'est necessaire au quotidien.")
    bullet("Les services tournent en local sur 8000, 8001 et 8080.")

    return items


def paginate(items: list[tuple[str, int, str]]) -> list[list[tuple[str, int, str]]]:
    pages: list[list[tuple[str, int, str]]] = []
    page: list[tuple[str, int, str]] = []
    current_height = 0
    max_height = 690

    for text, size, style in items:
        line_height = max(10, math.ceil(size * 1.45))
        if current_height + line_height > max_height and page:
            pages.append(page)
            page = []
            current_height = 0
        page.append((text, size, style))
        current_height += line_height
    if page:
        pages.append(page)
    return pages


def main() -> None:
    global text_positions
    writer = PdfWriter()
    pages = paginate(build_lines())
    for page_index, page in enumerate(pages, start=1):
        text_positions = []
        y = 738
        for _, size, _ in page:
            text_positions.append(y)
            y -= max(10, math.ceil(size * 1.45))
        page_with_footer = page + [(f"Page {page_index} / {len(pages)}", 8, "regular")]
        text_positions.append(36)
        writer.add_page(page_with_footer)
    writer.write(OUTPUT)
    print(OUTPUT)


if __name__ == "__main__":
    text_positions: list[int] = []
    main()
