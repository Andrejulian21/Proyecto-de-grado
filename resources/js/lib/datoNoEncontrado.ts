/**
 * Informative copy when a displayed field cannot be resolved from the API/DB.
 * Pass the full subject including article, e.g. "El director", "La modalidad".
 */
export function datoNoEncontrado(dato: string): string {
    return `${dato} no se ha podido encontrar.`;
}
