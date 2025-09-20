import argparse
import random


def main() -> None:
    """Juego simple para adivinar un número entre 1 y 100."""
    parser = argparse.ArgumentParser(description="Adivina el número")
    parser.add_argument(
        "--seed",
        type=int,
        default=None,
        help="Semilla para hacer reproducible el juego durante pruebas",
    )
    args = parser.parse_args()

    if args.seed is not None:
        random.seed(args.seed)

    target = random.randint(1, 100)
    print("Bienvenido al juego Adivina el Número!")
    attempts = 0

    while True:
        try:
            guess = int(input("Introduce un número entre 1 y 100: "))
        except ValueError:
            print("Por favor, introduce un número válido.")
            continue

        attempts += 1
        if guess < target:
            print("Demasiado bajo.")
        elif guess > target:
            print("Demasiado alto.")
        else:
            print(f"¡Correcto! Adivinaste el número en {attempts} intentos.")
            break


if __name__ == "__main__":
    main()
